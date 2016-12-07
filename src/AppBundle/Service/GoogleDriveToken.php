<?php

/*
 * This file is part of the SncRedisBundle package.
 *
 * (c) Henrik Westphal <henrik.westphal@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppBundle\Service;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;


class GoogleDriveToken
{
    public function getClient() {
        if( ! defined('APPLICATION_NAME'))
        {
            define('APPLICATION_NAME', 'ApplicationTestDrive1');

            define('CREDENTIALS_PATH', '/data/tmp/client_secret.json');
            define('CLIENT_SECRET_PATH', '/data/tmp/client_id.json');
        }

        $client = new \Google_Client();
        $client->setApplicationName(APPLICATION_NAME);
        $client->addScope(\Google_Service_Sheets::SPREADSHEETS);
        $client->setAuthConfig(CLIENT_SECRET_PATH);
        $client->setAccessType('offline');

        // Load previously authorized credentials from a file.
        //$credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
        $credentialsPath = CREDENTIALS_PATH;
        
        try {
            if (file_exists($credentialsPath))
            {
                $accessToken = json_decode(file_get_contents($credentialsPath), true);
                $client->setAccessToken($accessToken);

                if ($client->isAccessTokenExpired()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
                }
                
            }else{
                throw new \Exception();
            }
        }
        catch(\Exception $e)
        {        
            // Request authorization from the user.
            //$client->setRedirectUri("https://www.chooo7.com/aouthcallback");
            $client->setRedirectUri("https://example.com");

            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            if(!file_exists(dirname($credentialsPath))) {
                mkdir(dirname($credentialsPath), 0700, true);
            }
            file_put_contents($credentialsPath, json_encode($accessToken));
            printf("Credentials saved to %s\n", $credentialsPath);
            
            if ($client->isAccessTokenExpired()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
            }
        }
        
        // Refresh the token if it's expired.
        
        return $client;
    }

}