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

        $app->view->enqueueStyle(
            "app-v2",
            "ValuersManagement-v2",
            "css/plugin-ValuersManagement.css",
        );

        $self = $this;

        $app->hook(
            "component(opportunity-phase-config-evaluation).evaluation-step-header:end",
            function () {
                $entity = $this->controller->requestedEntity;
                $this->part("evalmaster--upload", ["entity" => $entity]);
            },
        );

        $app->hook("GET(opportunity.valuersmanagement)", function () use (
            $self,
            $app,
        ) {
            ini_set("max_execution_time", "0");
            $this->requireAuthentication();

            $opportunity = $app
                ->repo("Opportunity")
                ->find($this->data["entity"]);
            if (!$opportunity) {
                $app->log->error(
                    "[ValuersManagement] Oportunidade não encontrada",
                );
                $app->pass();
            }

            $opportunity->checkPermission("@control");

            $request = $this->data;
            $self->pluginLog(
                "[Hook] Requisição recebida: " . json_encode($request),
            );

            if ($self->valuersmanagement($request)) {
                $this->json(true);
            }
        });
    }

    protected function pluginLog(string $message)
    {
        $logDir = __DIR__ . "/logs";
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . "/valuersmanagement.log";
        $date = date("Y-m-d H:i:s");
        $formattedMessage = "[$date] $message" . PHP_EOL;

        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }

    public function valuersmanagement($request)
    {
        $app = App::i();
        $this->pluginLog(
            "[INICIO] valuersmanagement - request: " . json_encode($request),
        );

        $file = $app->repo("File")->find($request["file"]);
        if (!$file) {
            $this->pluginLog(
                "[ERRO] Arquivo não encontrado: ID " . $request["file"],
            );
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
            $this->buildList($data, $file->owner);
            $this->pluginLog("[OK] buildList executado");

            // Deleta o arquivo após o uso, como no plugin original
            $app->repo("File")->find($request["file"])->delete(true);
            $this->pluginLog("[OK] Arquivo deletado.");
        }

        $this->pluginLog("[FIM] valuersmanagement");
        return true;
    }

    public function buildList($values, Opportunity $opportunity)
    {
        $app = App::i();
        $this->pluginLog(
            "[buildList] Iniciado com " . count($values) . " linhas.",
        );

        // Agrupa os avaliadores por número de inscrição, como no plugin original
        $groupedData = [];
        foreach ($values as $item) {
            $number = $this->getNumber($item);
            if ($number) {
                $groupedData[$number] = $groupedData[$number] ?? [];
                $agentId = $this->getAgent($item);
                if ($agentId) {
                    $groupedData[$number][] = $agentId;
                }
            }
        }

        $allValuerUserIds = [];
        $conn = $app->em->getConnection();

        foreach ($groupedData as $number => $agentIds) {
            try {
                $this->pluginLog("[buildList] Processando inscrição $number.");

                $registration = $app->repo("Registration")->findOneBy([
                    "opportunity" => $opportunity,
                    "number" => $number,
                ]);

                if (!$registration) {
                    $this->pluginLog(
                        "[buildList][WARN] Inscrição $number não encontrada.",
                    );
                    continue;
                }

                // Obtém os user_id dos agent_id fornecidos
                $ids = implode(", ", array_unique($agentIds));
                $users = $conn->fetchFirstColumn(
                    "SELECT user_id FROM agent WHERE id IN ($ids)",
                );

                if (empty($users)) {
                    $this->pluginLog(
                        "[buildList][WARN] Nenhum usuário encontrado para os agentes na inscrição $number.",
                    );
                    continue;
                }

                // **PASSO CRUCIAL: Atribui a permissão à inscrição**
                $registration->valuersExcludeList = [];
                $registration->valuersIncludeList = array_map(function ($item) {
                    return "$item";
                }, $users);

                $registration->save(true);
                // $app->em->clear(); // Limpa o cache do Doctrine

                // Recarrega a inscrição para garantir que está gerenciada pelo EntityManager
                $registration = $app
                    ->repo("Registration")
                    ->find($registration->id);

                // Opcional: A criação da entidade RegistrationEvaluation
                foreach ($users as $userId) {
                    $existingEval = $app
                        ->repo("RegistrationEvaluation")
                        ->findOneBy([
                            "registration" => $registration,
                            "user" => $app->repo("User")->find($userId),
                        ]);

                    if (!$existingEval) {
                        $committee = $this->getCommitteeFromAgent(
                            $opportunity,
                            array_search($userId, $users),
                        );
                        $evaluation = new \MapasCulturais\Entities\RegistrationEvaluation();
                        $evaluation->registration = $registration;
                        $evaluation->user = $app->repo("User")->find($userId);
                        $evaluation->committee = $committee;
                        $evaluation->createTimestamp = new \DateTime();
                        $app->em->persist($evaluation);
                    }
                }
                $app->em->flush();

                $this->pluginLog(
                    "[buildList] Avaliadores definidos para inscrição $number: " .
                        implode(", ", $users),
                );

                // Coleta todos os user_id para a atualização de cache final
                $allValuerUserIds = array_merge($allValuerUserIds, $users);
            } catch (\Throwable $e) {
                $this->pluginLog(
                    "[buildList][ERRO] Exceção na inscrição $number: " .
                        $e->getMessage(),
                );
            }
        }

        // Atualiza o cache dos avaliadores para as oportunidades
        $allValuerUserIds = array_unique($allValuerUserIds);
        $usersToUpdate = $app
            ->repo("User")
            ->findBy(["id" => $allValuerUserIds]);
        $opportunity->enqueueToPCacheRecreation($usersToUpdate);

        $this->pluginLog("[buildList] Finalizado.");
    }

    protected function getCommitteeFromAgent(Opportunity $opportunity, $agentId)
    {
        $app = App::i();

        $emc = $app
            ->repo("EvaluationMethodConfiguration")
            ->findOneBy(["opportunity" => $opportunity]);

        if (!$emc) {
            $this->pluginLog(
                "[getCommitteeFromAgent] Configuração de avaliação não encontrada.",
            );
            return null;
        }

        $result = $app->em
            ->getConnection()
            ->fetchAssociative(
                "SELECT type FROM agent_relation WHERE object_type = 'MapasCulturais\\Entities\\EvaluationMethodConfiguration' AND object_id = :objectId AND agent_id = :agentId",
                [
                    "objectId" => $emc->id,
                    "agentId" => $agentId,
                ],
            );

        if ($result && isset($result["type"])) {
            return $result["type"];
        }

        return null;
    }

    function getNumber($item)
    {
        foreach ($item as $key => $value) {
            if (
                in_array(mb_strtolower($key), [
                    "inscrição",
                    "inscricao",
                    "number",
                    "número",
                ])
            ) {
                return $value;
            }
        }
        return null;
    }

    function getAgent($item)
    {
        foreach ($item as $key => $value) {
            if (
                in_array(mb_strtolower($key), [
                    "agente",
                    "id do agente",
                    "id do avaliador",
                ])
            ) {
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
                $this->pluginLog(
                    "[getSpreadsheetData] Cabeçalho detectado: " .
                        json_encode($header),
                );
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
                if ($cellValue !== null && $cellValue !== "") {
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
            "evalmaster",
            [
                '^text/csv$',
                '^application/vnd.ms-excel$',
                "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            ],
            "O arquivo enviado não é válido.",
            true,
            null,
            true,
        );
        $app->registerFileGroup("opportunity", $file_group_definition);
    }
}
