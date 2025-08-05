<?php

namespace ValuersManagement;

use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Plugin extends \MapasCulturais\Plugin
{
    public function _init()
    {
        $app = App::i();

        $app->view->enqueueStyle('app-v2', 'ValuersManagement-v2', 'css/plugin-ValuersManagement.css');

        $self = $this;

        $app->hook("component(opportunity-phase-config-evaluation).evaluation-step-header:end", function () {
            $entity = $this->controller->requestedEntity;
            $this->part('evalmaster--upload', ['entity' => $entity]);
        });

        $app->hook('GET(opportunity.valuersmanagement)', function () use ($self, $app) {
            ini_set('max_execution_time', '0');
            $this->requireAuthentication();

            $opportunity = $app->repo('Opportunity')->find($this->data['entity']);
            if (!$opportunity) {
                $app->log->error('[ValuersManagement] Oportunidade não encontrada');
                $app->pass();
            }

            $opportunity->checkPermission('@control');

            $request = $this->data;
            $self->pluginLog("[Hook] Requisição recebida: " . json_encode($request));

            if ($self->valuersmanagement($request)) {
                $this->json(true);
            }
        });
    }

    protected function pluginLog(string $message)
    {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . '/valuersmanagement.log';
        $date = date('Y-m-d H:i:s');
        $formattedMessage = "[$date] $message" . PHP_EOL;

        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }

    public function valuersmanagement($request)
    {
        $app = App::i();
        $this->pluginLog("[INICIO] valuersmanagement - request: " . json_encode($request));

        $file = $app->repo("File")->find($request['file']);
        if (!$file) {
            $this->pluginLog("[ERRO] Arquivo não encontrado: ID " . $request['file']);
            return true;
        }

        $this->pluginLog("[OK] Arquivo encontrado: " . $file->getPath());

        $spreadsheet = IOFactory::load($file->getPath());
        $this->pluginLog("[OK] Planilha carregada");

        $data = $this->getSpreadsheetData($spreadsheet);
        $this->pluginLog("[OK] Linhas extraídas: " . count($data));

        if (empty($data)) {
            $this->pluginLog("[WARN] Planilha vazia após leitura.");
        } else {
            $this->pluginLog("[INFO] Arquivo NÃO deletado (teste)");
            $this->buildList($data, $file->owner);
            $this->pluginLog("[OK] buildList executado");
        }

        $this->pluginLog("[FIM] valuersmanagement");
        return true;
    }

    public function buildList($values, Opportunity $opportunity)
    {
        $app = App::i();
        $this->pluginLog("[buildList] Iniciado com " . count($values) . " linhas.");

        foreach ($values as $idx => $item) {
            try {
                $this->pluginLog("[buildList] Processando linha $idx: " . json_encode($item));

                $number = $this->getNumber($item);
                $agent = $this->getAgent($item);

                if (!$number || !$agent) {
                    $this->pluginLog("[buildList][WARN] Linha ignorada (inscrição ou avaliador ausente)." );
                    continue;
                }

                $registration = $app->repo('Registration')->findOneBy([
                    'opportunity' => $opportunity,
                    'number' => $number
                ]);

                if (!$registration) {
                    $this->pluginLog("[buildList][WARN] Inscrição $number não encontrada.");
                    continue;
                }

                $agentIds = is_array($agent) ? $agent : [$agent];

                foreach ($agentIds as $agentId) {
                    $userId = $app->em->getConnection()->fetchOne(
                        "SELECT user_id FROM agent WHERE id = :agentId",
                        ['agentId' => $agentId]
                    );

                    if (!$userId) {
                        $this->pluginLog("[buildList][WARN] Usuário não encontrado para agent_id: $agentId");
                        continue;
                    }

                    $committee = $this->getCommitteeFromAgent($opportunity, $agentId);

                    $this->pluginLog("[buildList] Agent ID: $agentId, User ID: $userId, Comissão: $committee");

                    // Linha alterada: removido 'committee' da consulta de busca
                    $existingEval = $app->repo('RegistrationEvaluation')->findOneBy([
                        'registration' => $registration,
                        'user' => $app->repo('User')->find($userId),
                    ]);

                    if ($existingEval) {
                        $this->pluginLog("[buildList] Avaliação já existe para user_id $userId. Pulando.");
                        continue;
                    }

                    $evaluation = new \MapasCulturais\Entities\RegistrationEvaluation();
                    $evaluation->registration = $registration;
                    $evaluation->user = $app->repo('User')->find($userId);
                    $evaluation->committee = $committee;
                    $evaluation->createTimestamp = new \DateTime();

                    $app->em->persist($evaluation);
                    $this->pluginLog("[buildList] Avaliação criada para user_id $userId.");
                }
            } catch (\Throwable $e) {
                $this->pluginLog("[buildList][ERRO] Exceção na linha $idx: " . $e->getMessage());
            }
        }

        $app->em->flush();
        $this->pluginLog("[buildList] Finalizado");
    }

    protected function getCommitteeFromAgent(Opportunity $opportunity, $agentId)
    {
        $app = App::i();

        $emc = $app->repo('EvaluationMethodConfiguration')->findOneBy(['opportunity' => $opportunity]);

        if (!$emc) {
            $this->pluginLog("[getCommitteeFromAgent] Configuração de avaliação não encontrada.");
            return null;
        }

        $result = $app->em->getConnection()->fetchAssociative(
            "SELECT type FROM agent_relation WHERE object_type = 'MapasCulturais\\Entities\\EvaluationMethodConfiguration' AND object_id = :objectId AND agent_id = :agentId",
            [
                'objectId' => $emc->id,
                'agentId' => $agentId
            ]
        );

        if ($result && isset($result['type'])) {
            return $result['type'];
        }

        return null;
    }

    function getNumber($item)
    {
        foreach ($item as $key => $value) {
            if (in_array(mb_strtolower($key), ['inscrição', 'inscricao', 'number', 'número'])) {
                return $value;
            }
        }
        return null;
    }

    function getAgent($item)
    {
        foreach ($item as $key => $value) {
            if (in_array(mb_strtolower($key), ['agente', 'id do agente', 'id do avaliador'])) {
                return $value;
            }
        }
        return null;
    }

    public function getSpreadsheetData($spreadsheet)
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $header = [];
        $data = [];
        $firstRow = true;

        foreach ($worksheet->getRowIterator() as $row) {
            if ($firstRow) {
                $header = $this->getSpreadsheetHeader($row);
                $this->pluginLog("[getSpreadsheetData] Cabeçalho detectado: " . json_encode($header));
                $firstRow = false;
                continue;
            }

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            $columnIndex = 0;
            foreach ($cellIterator as $cell) {
                $headerValue = $header[$columnIndex] ?? "col$columnIndex";
                $cellValue = $cell->getValue();
                if ($cellValue !== null && $cellValue !== '') {
                    $rowData[$headerValue] = $cellValue;
                }
                $columnIndex++;
            }

            if ($rowData) {
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
        $file_group_definition = new \MapasCulturais\Definitions\FileGroup(
            'evalmaster',
            ['^text/csv$', '^application/vnd.ms-excel$', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'O arquivo enviado não é válido.',
            true,
            null,
            true
        );
        $app->registerFileGroup('opportunity', $file_group_definition);
    }
}