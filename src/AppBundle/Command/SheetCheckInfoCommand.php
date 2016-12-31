<?php

/*
 * This file is part of the SncRedisBundle package.
 *
 * (c) Henrik Westphal <henrik.westphal@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;


class SheetCheckInfoCommand extends ContainerAwareCommand
{

    
    protected $errors = array();
    
    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $startTime = time();
        $this->input = $input;
        $this->output = $output;
        $ecomInfoSer = $this->getContainer()->get('ecom.info');
        $ecomSheet = $this->getContainer()->get('ecom.sheet_updater');

        $docId = $this->input->getOption('doc');
        $category = $this->input->getOption('category');
        if(empty($category))
        {
            $category = $this->guessCategoryFromDoc($docId);
        }
        
        $sheets = $ecomSheet->getSheets($docId);
        foreach($sheets as $sheet)
        {
            $this->output->writeln("starting sheet ".$sheet['title']."");
            
            $letters = 'abcdefghijklmnopqrstuvwxyz';
            $columnForPrice = null;
            $columnForUrl = null;
            $columnForDate = null;
            $headers = $ecomSheet->getSheetValue($docId, "A1:Z1", $sheet['title']);
            $headers = $headers[0];
            
            $columnIndexes = array();
            
            for ($iColumn = 0; $iColumn < 24; $iColumn++)
            {
                $letter = $letters{$iColumn};
                //$value = $ecomSheet->getSheetValue($docId, $letter . "1", $sheet['title']);
                $value = array_key_exists($iColumn, $headers) ? $headers[$iColumn] : null;

                foreach ($this->getColumnsAssociation($category) as $internalIndex => $possibleExcelKeys)
                {
                    foreach ($possibleExcelKeys as $possibleExcelKey)
                    {
                        if (strtolower($value) == strtolower($possibleExcelKey))
                        {
                            $columnIndexes[$internalIndex] = $letter;
                            break 2;
                        }
                    }
                }
            }

            if (empty($columnIndexes))
            {
                throw new \Exception("Unable to find any column");
            }

            if (! array_key_exists('ean', $columnIndexes))
            {
                throw new \Exception("Unable to find EAN column");
            }
            
            $nbPerPacket = 20;
            $packetIndex = 0;
            $continueScanning = true;
            while($continueScanning)
            {
                $lineNumber = 2 + $packetIndex * $nbPerPacket;
                $maxLineNumber = $lineNumber + $nbPerPacket - 1;
                $rangeEan = $columnIndexes['ean'] . $lineNumber . ':' . $columnIndexes['ean'] . $maxLineNumber;
                $readedInfos = array();
                
                $eans = $ecomSheet->getSheetValue($docId, $rangeEan, $sheet['title']);
                foreach($columnIndexes as $k=>$columnIndex)
                {
                    $range = $columnIndex . $lineNumber . ':' . $columnIndex . $maxLineNumber;
                    $_values = $ecomSheet->getSheetValue($docId, $range, $sheet['title']);
                    $values = array();
                    if(is_array($_values))
                    {
                        foreach ($_values as $v)
                        {
                            if( ! empty($v))
                            {
                                $values[] = $v[0];
                            }
                        }
                    }
                    $readedInfos[$k] = $values; 
                }
                
                if ($eans === null)
                {
                    break;
                }

                $packetIndex++;

                foreach ($eans as $k => $data)
                {
                    $eans[$k] = $data[0];
                }
                if (count(array_filter($eans)) == 0)
                {
                    $continueScanning = false;
                }
                
                
                foreach ($eans as $localIndex => $ean)
                {
                    $globalLineNumber = $lineNumber + $localIndex;
                    
                    $detectedInfos = $ecomInfoSer->getInfos($ean, $category);
                    
                    foreach($detectedInfos as $columnName=>$detectedValues)
                    {
                        if( ! array_key_exists($columnName, $readedInfos))
                        {
                            //column no present in excel file
                            continue;
                        }
                        $actualValue = $readedInfos[$columnName][$localIndex];
                        $valueIsDetected = in_array($actualValue, $detectedValues) || in_array(strtolower($actualValue), array_map('strtolower', $detectedValues));
                        if( ! $valueIsDetected && ! in_array($columnName, array('brand', 'model')))
                        {
                            $errorMessage = 'Onglet '.$sheet['title'].' Ligne '.$globalLineNumber.' - '.
                              $columnName . ' : actualValue : '.$actualValue.'. Detected value : ';
                            foreach($detectedValues as $provider=>$value)
                            {
                                $_url = $detectedInfos['uri'][$provider];
                                $errorMessage.= '<br /> - '.$value.' (<a href="'.$_url.'">'.$_url.'</a>';
                            }
                            $this->errors[] = $errorMessage;
                            $this->output->writeln($errorMessage);
                        }
                    }
                }
                $this->output->writeln("sheet " . $sheet['title'] . " done");
            }            
        }
        
        $this->sendMailWithErrors();
        
        $this->output->writeln("DONE");
    }
    
    protected function getColumnsAssociation($category)
    {
        $associations = array();
        switch($category)
        {
            case 'aspirateur':
                //$associations['uri'] = array("Lien pour acheter");
                $associations['brand'] = array("brand");
                $associations['model'] = array("model");
                $associations['ean'] = array("ean");
                $associations['solDur'] = array("Sol dur (Hard floor)");
                $associations['tapis'] = array("Tapis (Carpet)");
                $associations['energyClass'] = array("class");
                $associations['bruit'] = array("Bruit (dB)");
                $associations['filtration'] = array("Poussière (Dust)");
                $associations['sac'] = array("à sac");
            break;
        }
        return $associations;
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('ecomscraper:sheet:checkinfo');
        $this->setDescription('Check sheet info');


        $this->addOption(
          'category',
          'c',
          InputOption::VALUE_REQUIRED,
          'Category of product',
          null
        );
        
        $this->addOption(
          'doc',
          'd',
          InputOption::VALUE_REQUIRED,
          'Document segment',
          null
        );

        $this->addOption(
          'email',
          'm',
          InputOption::VALUE_OPTIONAL,
          'Send error report to this email',
          null
        );
        
    }
    
    protected function sendMailWithErrors()
    {
        if(empty($this->errors))
        {
            return;
        }
        $apiKey = $this->getContainer()->getParameter('sendgrid_api_key');
        $from = $this->getContainer()->getParameter('alert_mail_from');
        $to = $this->getContainer()->getParameter('alert_mail_to');
        $toCommand = $this->input->getOption('email');

        $tos = array_filter(explode(';', str_replace(',', ';', $to)));
        $tos = array_merge($tos, array_filter(explode(';', str_replace(',', ';', $toCommand))));
        
        if(empty($tos))
        {
            return ;
        }
        
        $this->sendGrid = new \SendGrid\Client($apiKey);

        $docId = $this->input->getOption('doc');
        $documentLink = 'https://docs.google.com/spreadsheets/d/'.$docId.'/edit';

        $subject = "Detect info errors";
        $body = "<h1>Errors</h1>";
        $body .= "\n".'<div>';
        $body .= "\n".'<p>Document : <a href="'.$documentLink.'">'.$documentLink.'</a></p>';
        $body .= "\n".'</div>';
        $body .= "\n".'<div>';
        $body .= "\n".'<ul>';
        foreach($this->errors as $error)
        {
            $body .= "\n".'<li>'.$error.'</li>';
        }
        $body .= "\n".'</ul>';
        $body .= "\n".'</div>';
        
        $email = new \SendGrid\Email();
        foreach($tos as $to)
        {
            var_dump($body);
            $email->addTo($to)->setFrom($from)->setSubject($subject)->setHtml($body);

            $resp = $this->sendGrid->send($email);
        }
        
    }
    
    protected function guessCategoryFromDoc($docId)
    {
        switch($docId)
        {
            case '1YTys0LNJt4OgHzDErSP_TTtzA49_9yxMhqILDph2_VA':
                return 'aspirateur';
        }
        return null;
    }
}