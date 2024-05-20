<?php

namespace QuestApi\Helpers;

use QuestApi\Controllers\ConfigController;
use React\Http\Browser;

class ModSurveilance
{
    private static string $curseforgeBaseUri = 'https://api.curseforge.com';

    public static function CheckAllMods()
    {
        $config = (new ConfigController)->get();
        $modSurveilce = $config['ModSurveilce'];

        $modIds = array_keys($modSurveilce['mods']);

        $requestBody = [
            'modIds' => $modIds,
            'filterPcOnly' => false
        ];

        $client = new Browser();

        $response = $client->post(
            self::$curseforgeBaseUri . '/v1/mods',
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'x-api-key' => $modSurveilce['apiKey']
            ],
            json_encode($requestBody)
        )->then(
            function ($response) use ($modSurveilce, $config) {
                $mods = json_decode($response->getBody())->data;

                foreach ($mods as $mod) {
                    $modId = $mod->id;
                    $latestFileId = $mod->latestFiles[0]->id;

                    $modInfo = $modSurveilce['mods'][$modId];

                    $changed = false;

                    if (!isset($modInfo['latestFileId'])) {
                        $changed = true;
                    } elseif ($modInfo['latestFileId'] != $latestFileId) {
                        $changed = true;
                    }

                    if ($changed) {
                        $client = new Browser();
                        $response = $client->get(
                            self::$curseforgeBaseUri . '/v1/mods/' . $modId . '/files/' . $latestFileId . '/changelog',
                            [
                                'Content-Type' => 'application/json',
                                'x-api-key' => $modSurveilce['apiKey']
                            ]
                        )->then(
                            function ($response) use ($modId, $mod, $modSurveilce, $latestFileId) {
                                $changelog = json_decode($response->getBody())->data;
                                $changelog = str_replace('<br>', "\n", $changelog);
                                $changelog = strip_tags($changelog);
                                $changelog = htmlspecialchars_decode($changelog);

                                $data = [
                                    'content' => "",
                                    'embeds' => [
                                        [
                                            'title' => "Changelog",
                                            'description' => $changelog,
                                            'author' => [
                                                'name' => "New update for $mod->name",
                                            ],
                                            'thumbnail' => [
                                                'url' => $mod->logo->url
                                            ],
                                            'footer' => [
                                                'text' => 'Powered by ArkAscendedQuestApi'
                                            ],
                                            'timestamp' => date('Y-m-d\TH:i:s\Z'),
                                            "color" => 2354023,
                                        ],
                                    ],
                                    "username" => "ArkAscendedQuestApi",
                                ];

                                $client = new Browser();
                                $client->post($modSurveilce['discordWebhook'], [
                                    'Content-Type' => 'application/json'
                                ], json_encode($data, JSON_UNESCAPED_SLASHES))->then(
                                    function ($response) use ($modId, $latestFileId) {
                                        $config = ConfigController::get();
                                        $config['ModSurveilce']['mods'][$modId]['latestFileId'] = $latestFileId;
                                        ConfigController::set($config);
                                    },
                                    function ($e) {
                                        echo $e->getMessage() . PHP_EOL;
                                        echo $e->getResponse()->getBody() . PHP_EOL;
                                    }
                                );
                            },
                        );
                    }
                }
            },
            function ($e) {
                echo $e->getMessage();
            }
        );
    }
}
