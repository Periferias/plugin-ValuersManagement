<?php

namespace ValuersManagement;

use MapasCulturais\App;
use MapasCulturais\Definitions\FileGroup;
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

        $app->hook(" template(opportunity.edit.add-entity-evaluator-committee):after", function () {
            $entity = $this->controller->requestedEntity;
            $this->part('evalmaster--upload', ['entity' => $entity]);
        });

        $app->hook('GET(opportunity.valuersmanagement)', function () use ($self) {
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
            $this->buildList($data);
        }
    }

    public function buildList($values)
    {

        $result = [];
        $action = null;
        $clear = [];
        foreach ($values as $value) {
            $action = $this->getAction($value);
            $registration = $value['REGISTRATION'];

            if ($action == "clear") {
                $clear[] = $registration;
            }
        }

        $result['CLEAR'] = $clear;

        foreach ($values as $value) {
            $action = $this->getAction($value);
            $agent = $value['AGENTE'];
            $registration = $value['REGISTRATION'];

            if (in_array($registration, $clear)) {
                continue;
            }

            if ($action == "include") {
                $result[$registration]['INCLUDE'][] = $agent;
            }

            if ($action == "exclude") {
                $result[$registration]['EXCLUDE'][] = $agent;
            }
        }


        return $result;
    }

    public function getAction($value)
    {
        return empty($value['ACTION']) ? "include" : mb_strtolower($value['ACTION']);
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
                $rowData[$headerValue] = $cellValue;
                $columnIndex++;
            }

            $data[] = $rowData;
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

    public function registerTaxonomies()
    {
    }
}
