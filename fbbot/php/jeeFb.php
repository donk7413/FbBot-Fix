<?php

/* This file is part of the Jeedom Facebook Messenger plugion.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */


header('Content-type: application/json');
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

$verify_token     = jeedom::getApiKey('fbbot');
$hub_verify_token = null;

if (!isset($_REQUEST['hub_challenge']) && !isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
    echo "payload error";
    die();
}

if (isset($_REQUEST['hub_challenge'])) {
    $challenge        = $_REQUEST['hub_challenge'];
    $hub_verify_token = $_REQUEST['hub_verify_token'];

    if ($hub_verify_token === $verify_token) {
        echo $challenge;
        die();
    } else {
        echo "Token de vérification KO";
        die();
    }
}

if (isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
    $content = file_get_contents('php://input');
    $json    = json_decode($content, true);

    $eqLogic = fbbot::byLogicalId($json['entry'][0]['id'], 'fbbot');
    if (!is_object($eqLogic)) {
        echo json_encode(array('text' => __('Page inconnue : ', __FILE__) . $json['entry'][0]['id']));
        die();
    }

    if ("sha1=" . hash_hmac('sha1', $content, $eqLogic->getConfiguration('app_secret')) != $_SERVER['HTTP_X_HUB_SIGNATURE']) {
        die();
    }
}

$access_token = $eqLogic->getConfiguration('access_token');


foreach ($json['entry'] as $entry) {
    foreach ($entry['messaging'] as $messaging) {

     
      
      
     
      //Test de la valeur de message Si vide  = Requête annulée (Comportement normal après une demande qui a fonctionnée)//(modif de donk7413)
       if($messaging['message'] == null || empty($messaging['message']))
      {
        log::add('fbbot', 'debug', 'Le contenu du message est vide. ');
      }
      
      
        if(!empty($messaging['message']) && $messaging['message'] != null)//(modif de donk7413)
      {
          log::add('fbbot', 'debug', 'Traitement de message(s) reçu(s) et valide(s)');//(modif de donk7413)
          log::add('fbbot', 'debug', 'Traitement du message : ' . $messaging['message']['text']);//(modif de donk7413)
      }

        $sender            = $messaging['sender']['id'];
        $message           = $messaging['message']['text'];
        $page_id           = $messaging['recipient']['id'];
        $quickReplyPayLoad = $messaging['message']['quick_reply']['payload'];

       //if (isset($message) && isset($sender)) 
        if (isset($message) && isset($sender)) {
            $cmd_text = $eqLogic->getCmd('info', 'text');
            $cmd_text->event($message);

            $cmd_sender = $eqLogic->getCmd('info', 'sender');
            $cmd_sender->event($sender);
        } else {
            continue;
        }

        // gestion des quick Replies
        foreach ($eqLogic->getCmd('action') as $cmd) {
            if (isset($quickReplyPayLoad) && $cmd->askResponse($message)) {
               log::add('fbbot', 'debug', 'Traitement de la réponse rapide : ' . $message);//(modif de donk7413)
              
                echo json_encode(array('text' => ''));
              
              //(modif de donk7413)
               $cmd_text = $eqLogic->getCmd('info', 'text');
               $cmd_text->event('');
               $cmd_text = $eqLogic->getCmd('info', 'text');
              log::add('fbbot', 'debug', ' Réponse traitée - Reset de la variable Jeedom message ... ');
              //(modif de donk7413)
            
                continue 2;
            }
        }

        // vérification de l'utlisateur demandeur
        $cmd_user = $eqLogic->getCmd('action', $sender);
        if (!is_object($cmd_user)) {
            if ($eqLogic->getConfiguration('isAccepting') == 1) {
                $cmd_user = new fbbotCmd();
                $cmd_user->setLogicalId($sender);
                $cmd_user->setIsVisible(1);
                $cmd_user->setName("New user");
                $cmd_user->setConfiguration('interact', 0);
                $cmd_user->setConfiguration('fb_user_id', $sender);
                $cmd_user->setConfiguration('jeedom_username', 'admin');
                $cmd_user->setType('action');
                $cmd_user->setSubType('message');
                $cmd_user->setEqLogic_id($eqLogic->getId());
                $cmd_user->save();
            } else {
                continue;
            }
        }

        $user_profile = $cmd_user->getConfiguration('jeedom_username') != "" ? $cmd_user->getConfiguration('jeedom_username') : null;
        $parameters   = array();

        $user = user::byLogin($user_profile);
        if (is_object($user)) {
            $parameters['profile'] = $user_profile;
        }
      
      //test de message //(modif de donk7413)
      log::add('fbbot', 'debug', 'Message vaut : ' . $message);
		
      //Intéraction SI message n'est pas null ou vide //(modif de donk7413)
        if ($cmd_user->getConfiguration('interact') == 1 && !empty($message)) {//(modif de donk7413)
            $parameters['plugin'] = 'fbbot';
            log::add('fbbot', 'debug', 'Interaction ' . print_r($reply, true));
            $result_jeedom        = interactQuery::tryToReply(trim($message), $parameters);
            if (is_array($result_jeedom)) {
                $message_to_reply .= implode($result_jeedom);
            } else {
                $message_to_reply .= $result_jeedom;
            }
        } else {
            $message_to_reply = 'Utilisateur non habilité';
        }
      
      //test de réponse (modif de donk7413)
      log::add('fbbot', 'debug', 'Réponse vaut : ' . $message_to_reply);

        //API Url
        $url             = 'https://graph.facebook.com/v2.6/me/messages?access_token=' . $access_token;
        //Initiate cURL.
        $ch              = curl_init($url);
        //The JSON data.
        $jsonData        = '{
           "messaging_type": "RESPONSE",
		   "recipient":{
		        "id":"' . $sender . '"
		    },
		    "message":{
		        "text":"' . $message_to_reply . '"
		    }
		}';
        //Encode the array into JSON.
        $jsonDataEncoded = $jsonData;
        //Tell cURL that we want to send a POST request.
        curl_setopt($ch, CURLOPT_POST, 1);
        //Attach our encoded JSON string to the POST fields.
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
        //Set the content type to application/json
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        //Execute the request
        if (!empty($messaging['message']) && $messaging != null) { //(modif de donk7413)
            $result = curl_exec($ch);
            log::add('fbbot', 'debug', 'Envoi de la réponse - résultat : ' . $result);
          //reset de message(modif de donk7413)
            $cmd_text = $eqLogic->getCmd('info', 'text');
            $cmd_text->event('');
          log::add('fbbot', 'debug', 'Reset de la variable Jeedom message ... ');
        }
    }
}
