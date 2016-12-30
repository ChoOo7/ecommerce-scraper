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


class SheetUpdateCommand extends ContainerAwareCommand
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
        $ecomPriceSer = $this->getContainer()->get('ecom.price');
        $ecomSheet = $this->getContainer()->get('ecom.sheet_updater');

        $docId = $this->input->getOption('doc');
        
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
                } elseif ($value == "Date MAJ Prix")
                {
                    $columnForDate = $letter;
                }
                if ($columnForUrl && $columnForPrice && $columnForDate)
                {
                    break;
                }
            }

            if (empty($columnForPrice))
            {
                throw new \Exception("Unable to find price column");
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
                $rangePrice = $columnForPrice . $lineNumber . ':' . $columnForPrice . $maxLineNumber;

                $infosUrl = $ecomSheet->getSheetValue($docId, $rangeUrl, $sheet['title']);
                $infosPrice = $ecomSheet->getSheetValue($docId, $rangePrice, $sheet['title']);

                if ($infosUrl === null || $infosPrice === null)
                {
                    break;
                }

                $packetIndex++;

                foreach ($infosUrl as $k => $data)
                {
                    $infosUrl[$k] = $data[0];
                }
                foreach ($infosPrice as $k => $data)
                {
                    $infosPrice[$k] = $data[0];
                }
                if (count(array_filter($infosUrl)) == 0)
                {
                    $continueScanning = false;
                }

                foreach ($infosUrl as $localIndex => $url)
                {
                    $globalLineNumber = $lineNumber + $localIndex;
                    
                    $actualPrice = $infosPrice[$localIndex];
                    //$actualPrice = $ecomSheet->getSheetValue($docId, $columnForPrice . $lineNumber, $sheet['title']);

                    $actualPrice = $ecomPriceSer->formatPrice($actualPrice);
                    //$url = $ecomSheet->getSheetValue($docId, $columnForUrl . $lineNumber, $sheet['title']);
                    if (empty($url))
                    {
                        break;
                    }
                    $newPrice = $ecomPriceSer->getPrice($url);
                    if (empty($newPrice))
                    {
                        $this->errors[] = 'Onglet '.$sheet['title'].' Ligne '.$globalLineNumber.' - '.'No price detected for ' . $url . '';
                        $this->output->writeln('<error>No price detected for ' . $url . '</error>');
                    } else
                    {
                        if ($newPrice == $actualPrice)
                        {
                            $this->output->writeln('' . $url . ' ' . $actualPrice . ' - not modified');
                            if ($columnForDate)
                            {
                                $ecomSheet->setSheetValue($docId, $columnForDate . $globalLineNumber, date('Y-m-d H:i:s', $startTime), $sheet['title']);
                            }
                        } else
                        {
                            $evol = (round((100 * ($actualPrice - $newPrice) / $actualPrice)));
                            $this->output->writeln('' . $url . ' ' . $actualPrice . ' => ' . $newPrice . ' (' . $evol . '%)');
                            if ($evol < 20)
                            {
                                $ecomSheet->setSheetValue($docId, $columnForPrice . $globalLineNumber, $newPrice, $sheet['title']);
                                if ($columnForDate)
                                {
                                    $ecomSheet->setSheetValue($docId, $columnForDate . $globalLineNumber, date('Y-m-d H:i:s', $startTime, $sheet['title']));
                                }
                            }else{
                                $this->errors[] = 'Onglet '.$sheet['title'].' Ligne '.$globalLineNumber.' - '.'Variation de prix trop importante '.$actualPrice.' devient '.$newPrice.'  - '. $url . '';
                            }
                        }
                    }
                }
                $this->output->writeln("sheet " . $sheet['title'] . " done");
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

        $this->setName('ecomscraper:sheet:update');
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

        $subject = "Import result error";
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