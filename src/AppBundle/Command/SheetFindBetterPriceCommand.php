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


class SheetFindBetterPriceCommand extends ContainerAwareCommand
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
        $category = $this->input->getOption('category');
        

        $docId = $this->input->getOption('doc');
        
        $eanBestPrices = array();
        
        $sheets = $ecomSheet->getSheets($docId);
        foreach($sheets as $sheet)
        {
            $this->output->writeln("starting sheet ".$sheet['title']."");
            
            $letters = 'abcdefghijklmnopqrstuvwxyz';
            $columnForPrice = null;
            $columnForUrl = null;
            $columnForEan = null;
            $columnForModel = null;
            $headers = $ecomSheet->getSheetValue($docId, "A1:Z1", $sheet['title']);
            $headers = $headers[0];
            
            for ($iColumn = 0; $iColumn < 24; $iColumn++)
            {
                $letter = $letters{$iColumn};
                //$value = $ecomSheet->getSheetValue($docId, $letter . "1", $sheet['title']);
                $value = array_key_exists($iColumn, $headers) ? $headers[$iColumn] : null;
                if ($value == "Prix (€)" || $value == "Le meilleur prix")
                {
                    $columnForPrice = $letter;
                } elseif ($value == "Lien pour acheter")
                {
                    $columnForUrl = $letter;
                } elseif ($value == "ean")
                {
                    $columnForEan = $letter;
                } elseif ($value == "model")
                {
                    $columnForModel = $letter;
                }
                if ($columnForUrl && $columnForPrice && $columnForEan && $columnForModel)
                {
                    break;
                }
            }

            if (empty($columnForPrice))
            {
                throw new \Exception("Unable to find price column");
            }
            if (empty($columnForEan))
            {
                throw new \Exception("Unable to find ean column");
            }
            if (empty($columnForModel))
            {
                throw new \Exception("Unable to find model column");
            }
            if (empty($columnForUrl))
            {
                throw new \Exception("Unable to find url column");
            }

            $nbPerPacket = 50;
            $packetIndex = 0;
            $continueScanning = true;
            while($continueScanning)
            {
                $lineNumber = 2 + $packetIndex * $nbPerPacket;
                $maxLineNumber = $lineNumber + $nbPerPacket - 1;
                $rangeUrl = $columnForUrl . $lineNumber . ':' . $columnForUrl . $maxLineNumber;
                $rangeEan = $columnForEan . $lineNumber . ':' . $columnForEan . $maxLineNumber;
                $rangeModel = $columnForModel . $lineNumber . ':' . $columnForModel . $maxLineNumber;
                $rangePrice = $columnForPrice . $lineNumber . ':' . $columnForPrice . $maxLineNumber;

                $infosUrl = $ecomSheet->getSheetValue($docId, $rangeUrl, $sheet['title']);
                $infosEan = $ecomSheet->getSheetValue($docId, $rangeEan, $sheet['title']);
                $infosModel = $ecomSheet->getSheetValue($docId, $rangeModel, $sheet['title']);
                $infosPrice = $ecomSheet->getSheetValue($docId, $rangePrice, $sheet['title']);

                if ($infosEan === null)
                {
                    break;
                }

                $packetIndex++;

                foreach ($infosEan as $k => $data)
                {
                    $infosEan[$k] = $data[0];
                }

                foreach ($infosModel as $k => $data)
                {
                    $infosModel[$k] = $data[0];
                }
                if( ! is_array($infosPrice))
                {
                    $infosPrice = array();
                }
                foreach ($infosPrice as $k => $data)
                {
                    $infosPrice[$k] = $data[0];
                }
                if (count(array_filter($infosEan)) == 0)
                {
                    $continueScanning = false;
                }

                foreach ($infosEan as $localIndex => $url)
                {
                    $globalLineNumber = $lineNumber + $localIndex;
                    
                    $actualPrice = array_key_exists($localIndex, $infosPrice) ? $infosPrice[$localIndex] : 99999;
                    $ean = $infosEan[$localIndex];
                    $model = $infosModel[$localIndex];
                    if( ! array_key_exists($ean, $eanBestPrices) || $eanBestPrices[$ean]['actualBestPrice'] >= $actualPrice)
                    {
                        $eanBestPrices[$ean] = array('actualBestPrice'=>$actualPrice, 'line'=>$globalLineNumber, 'model'=>$model, 'sheet'=>$sheet['title']);
                    }
                }
                $this->output->writeln("sheet " . $sheet['title'] . " done");
            }            
        }
        
        $this->output->writeln("we are now about to try to find better prices");
        foreach($eanBestPrices as $ean => $lineInfos)
        {
            $actualBestPrice = $lineInfos['actualBestPrice'];
            $actualBestPrice = (float)$actualBestPrice;
            $infos = $ecomInfoSer->getInfos($ean, $category);
            if( ! array_key_exists('price', $infos))
            {
                continue;
            }
            foreach($infos['price'] as $provider=>$thePrice)
            {
                $url = $infos['uri'][$provider];
                $thePrice = (float)$thePrice;
                if($thePrice < $actualBestPrice)
                {
                    $errorMsg = 'Onglet '.$lineInfos['sheet'].' Ligne '.$lineInfos['line'].' - '.$lineInfos['model'].' better price detected '.$actualBestPrice.' => '.$thePrice.'  - '.$provider.' - '. $url . '';
                    $this->errors[] = $errorMsg;
                    $this->output->writeln($errorMsg);
                }
            }
        }
        
        $this->sendMailWithErrors();
        
        $this->output->writeln("DONE");
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('ecomscraper:sheet:findbetterprice');
        $this->setDescription('Update a sheet');


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

        $this->addOption(
          'category',
          'c',
          InputOption::VALUE_OPTIONAL,
          'Category of product',
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

        $subject = "WE HAVE BETTER PRICE 4You baby";
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
            $email->addTo($to)->setFrom($from)->setSubject($subject)->setHtml($body);

            $resp = $this->sendGrid->send($email);
        }
        
    }
}