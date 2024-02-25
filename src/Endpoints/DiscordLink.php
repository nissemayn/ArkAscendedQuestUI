<?php

namespace QuestApi\Endpoints;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tnapf\Router\Routing\RouteRunner;
use HttpSoft\Response\JsonResponse;
use Tnapf\Router\Interfaces\ControllerInterface;
use Exception;
use QuestApi\Controllers\DatabaseController;

class DiscordLink implements ControllerInterface
{
    public function handle(
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteRunner $route,
        ): ResponseInterface {
           
            $postParams = json_decode($request->getBody()->getContents());

            if ( !isset($postParams->activationcode) ) {
                return new JsonResponse([
                    'error' => 'Activationcode not provided.'
                ], 400);
            }

            $eos_id = $route->args->EOS_ID;
            $code = $postParams->activationcode;

            $db = DatabaseController::getConnection();

            $existingUsers = $db->query("SELECT * FROM discordlink WHERE eos_id = %s OR activationcode = %s", $eos_id, $code);

            if ( !empty($existingUsers) ) {

                foreach ($existingUsers as $user) {
                    if ($user['eos_id'] == $eos_id && $user['discord_id'] != null) {
                        return new JsonResponse([
                            'error' => 'EOS_ID already linked.'
                        ], 400);
                    }

                    else if ( $user ['eos_id'] == $eos_id && $user['activationcode'] != NULL ) {
                        if ( time() - $user['timestamp'] > (5*60) ) {
                            $db->delete("discordlink", "eos_id = %s", $eos_id);
                        }
                        else {
                            return new JsonResponse([
                                'error' => 'EOS_ID already waiting for activation.',
                                'activationcode' => $user['activationcode']
                            ], 400);
                        }
                    }
                }
            }

            try {
                $db->insert("discordlink", [
                    "eos_id" => $eos_id,
                    "activationcode" => $code,
                    "timestamp" => time()
                ]);
                
                $response = new JsonResponse([
                    'eos_id' => $eos_id,
                    'page' => 'discordlink',
                    'activationcode' => $code
                ]);

                return $response;
            }
            catch (Exception $e) {
                return new JsonResponse([
                    'error' => 'Database error: ' . $e->getMessage()
                ], 400);
            }
        }
}