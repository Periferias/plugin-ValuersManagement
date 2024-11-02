<?php

namespace ValuersManagement;

use MapasCulturais\App;
use MapasCulturais\Controllers\Opportunity as ControllersOpportunity;
use MapasCulturais\Definitions\FileGroup;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Registration;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Plugin extends \MapasCulturais\Plugin
{
    function __construct($config = [])
    {
        $config += [];

        parent::__construct($config);
    }


    public function _init()
    {
        $app = App::i();

        $self = $this;

        $app->hook("component(opportunity-phase-config-evaluation).evaluation-step-header:end", function () {
            $entity = $this->controller->requestedEntity;
            $this->part('evalmaster--upload', ['entity' => $entity]);
        });

        $app->hook('GET(opportunity.valuersmanagement)', function () use ($self, $app) {
            ini_set('max_execution_time', '0');
            
            /** @var ControllersOpportunity $this */
            $this->requireAuthentication();
            $opportunity = $app->repo('Opportunity')->find($this->data['entity']);
            if(!$opportunity) {
                $app->pass();
            }

            $opportunity->checkPermission('@control');

            $request = $this->data;
            $self->valuersmanagement($request);
        });
    }

    public function valuersmanagement($request)
    {
        $app = App::i();

        if ($file = $app->repo("File")->find($request['file'])) {
            $spreadsheet = IOFactory::load($file->getPath());
            $data = $this->getSpreadsheetData($spreadsheet);
            $this->buildList($data, $file->owner);
            
            $file = $app->repo("File")->find($request['file']);
            $file->delete(true);
        }

        echo "<script>window.history.back();</script>";
    }

    function getNumber($item) {
        $k = null;
        foreach($item as $key => $value) {
            if(in_array(mb_strtolower($key), ['inscrição', 'inscricao', 'number', 'número'])) {
                $k = $key;
                break;
            }
        }

        return $item[$k];
    }

    function getAgent($item) {
        $k = null;
        foreach($item as $key => $value) {
            if(in_array(mb_strtolower($key), ['agente', 'id do agente', 'id do avaliador'])) {
                $k = $key;
                break;
            }
        }

        return $item[$k];
    }

    public function buildList($values, Opportunity $opportunity) {
        $app = App::i();
        
        $data = [];

        foreach($values as $item) {
            $number = $this->getNumber($item);
            $data[$number] = $data[$number] ?? [];
            $data[$number][] = $this->getAgent($item);
        }

        $_eval_users_id = [];
        $n_total = count($data);
        $n = 0;
        foreach($data as $number => $valuers) {
            $n++;
            /** @var Registration $registration */
            $registration = $app->repo('Registration')->findOneBy(['opportunity' => $opportunity, 'number' => $number]);

            $ids = implode(', ', $valuers);

            $conn = $app->em->getConnection();
            $users = $conn->fetchFirstColumn("SELECT user_id FROM agent WHERE id in ($ids)");
            
            foreach($users as $usr){
                $_eval_users_id[] = $usr;
            }

            $registration->__skipQueuingPCacheRecreation = true;
         
            $registration->valuersExcludeList = [];
            $registration->valuersIncludeList = array_map(function($item) { return "$item"; }, $users);
            
            $registration->save(true);

            $app->log->debug("({$n}/{$n_total}) Definindo avaliadores para a inscrição $number: $ids");

            $app->em->clear();
        }

        $_eval_users_id = array_unique($_eval_users_id);

        $users = $app->repo('User')->findBy(['id' => $_eval_users_id]);
        $opportunity->enqueueToPCacheRecreation($users);
    }

    public function getSpreadsheetData($spreadsheet)
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $header = [];
        $firstRow = true;
        foreach ($worksheet->getRowIterator() as $row) {
            if ($firstRow) {
                $header = $this->getSpreadsheetHeader($row);
                $firstRow = false;
                continue;
            }

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            $columnIndex = 0;
            foreach ($cellIterator as $cell) {
                $headerValue = $header[$columnIndex];
                $cellValue = $cell->getValue();
                if($cellValue){
                    $rowData[$headerValue] = $cellValue;
                }
                $columnIndex++;
            }

            if($rowData){
                $data[] = $rowData;
            }
        }

        return $data;
    }

    public function getSpreadsheetHeader($row)
    {
        $header = [];
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        foreach ($cellIterator as $cell) {
            $header[] = $cell->getValue();
        }

        return $header;
    }

    public function register()
    {
        $app = App::i();

        $file_group_definition = new FileGroup('evalmaster', ['^text/csv$', '^application/vnd.ms-excel$', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], 'O arquivo enviado não é válido.', true, null, true);
        $app->registerFileGroup('opportunity', $file_group_definition);
    }
}
