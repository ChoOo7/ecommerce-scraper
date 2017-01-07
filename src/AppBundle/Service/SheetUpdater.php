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


class SheetUpdater
{
    protected $client;
    public function __construct($client)
    {
        $this->client = $client;
    }


    public function getSheets($spreadsheetId)
    {
        $sheets = array();
        $try = 0;
        $maxTry = 10;
        while ($try < $maxTry)
        {
            $try++;
            try
            {
                $client = $this->client->getClient();
                $service = new \Google_Service_Sheets($client);
                foreach($service->spreadsheets->get($spreadsheetId)->getSheets() as $sheet)
                {
                    $sheets[] = $sheet->getProperties();
                }
                return $sheets;
            }
            catch(\Exception $e)
            {
                echo "\n".$e->getMessage();
                if($try >= $maxTry)
                {
                    throw $e;
                }
            }
            sleep($try);
        }
        return $sheets;
    }
            

    public function getSheetValue($spreadsheetId, $cellIdentifier, $sheetId = null)
    {
        if($sheetId)
        {
            $cellIdentifier = $sheetId.'!'.$cellIdentifier;
        }
        $try = 0;
        $maxTry = 10;
        while($try < $maxTry)
        {
            $try ++;
            try
            {
                $client = $this->client->getClient();

                $service = new \Google_Service_Sheets($client);
                $sheets = $service->spreadsheets->get($spreadsheetId)->getSheets();
                $response = $service->spreadsheets_values->get($spreadsheetId, $cellIdentifier);
                $values = $response->getValues();
                if(strpos($cellIdentifier, ':') === false)
                {
                    return $values[0][0];
                }else{
                    return $values;
                }
            }
            catch(\Exception $e)
            {
                echo "\n".$e->getMessage();
                if($try >= $maxTry)
                {
                    throw $e;
                }
            }
            sleep($try);
        }
    }

    public function setSheetValue($spreadsheetId, $cellIdentifier, $cellValue, $sheetId = null)
    {
        if($sheetId)
        {
            $cellIdentifier = $sheetId.'!'.$cellIdentifier;
        }
        
        $try = 0;
        $maxTry = 10;
        while($try < $maxTry)
        {
            $try ++;
            try
            {
                $client = $this->client->getClient();

                $service = new \Google_Service_Sheets($client);

                $postBody = new \Google_Service_Sheets_ValueRange();
                $postBody->setRange($cellIdentifier);
                $postBody->setValues(array(array($cellValue)));
                $opts = array();
                $opts['valueInputOption'] = 'RAW';

                $service->spreadsheets_values->update($spreadsheetId, $cellIdentifier, $postBody, $opts);
                return ;
            }
            catch(\Exception $e)
            {
                echo "\n".$e->getMessage();
                if($try >= $maxTry)
                {
                    throw $e;
                }
            }

            sleep($try);
        }
    }
}