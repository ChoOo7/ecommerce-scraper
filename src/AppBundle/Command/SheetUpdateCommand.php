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
        $ecomInfoSer = $this->getContainer()->get('ecom.info');
        $ecomAffiSer = $this->getContainer()->get('ecom.affiliation');
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
            /*
            
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
            */

            $nbPerPacket = 50;
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
                /*
                if ($infosUrl === null || $infosPrice === null)
                {
                    break;
                }
                */

                $packetIndex++;
                if( ! is_array($eans))
                {
                    $continueScanning = false;
                    break;                    
                }
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
                    
                    //On recherche si l'on a des informations différentes ou si des infos sont manquantes
                    foreach($detectedInfos as $columnName=>$detectedValues)
                    {
                        if( ! array_key_exists($columnName, $readedInfos))
                        {
                            //column no present in excel file
                            continue;
                        }
                        if( ! array_key_exists($localIndex, $readedInfos[$columnName]))
                        {
                            $readedInfos[$columnName][$localIndex] = null;
                        }
                        $actualValue = $readedInfos[$columnName][$localIndex];
                        if(empty($actualValue))
                        {
                            //On va alors remplir le fichier excel
                            $newValue = null;

                            foreach ($detectedValues as $provider => $value)
                            {
                                //TODO : setValue
                                $newValue = $value;
                            }
                            if($newValue)
                            {
                                $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . $columnName . ' is empty, we fill it with : '.$newValue;
                                $this->errors[] = $errorMessage;
                                $this->output->writeln($errorMessage);
                                //TODO effectivement faire le set
                            }                            
                        }else
                        {
                            $valueIsDetected = in_array($actualValue, $detectedValues) || in_array(strtolower($actualValue), array_map('strtolower', $detectedValues));
                            if (!$valueIsDetected && !in_array($columnName, array('brand', 'model', 'uri', 'price')))
                            {
                                $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . $columnName . ' : actualValue : ' . $actualValue . '. Detected value : ';
                                foreach ($detectedValues as $provider => $value)
                                {
                                    $_url = $detectedInfos['uri'][$provider];
                                    $errorMessage .= '<br /> - ' . $value . ' (<a href="' . $_url . '">' . $_url . '</a>';
                                }
                                $this->errors[] = $errorMessage;
                                $this->output->writeln($errorMessage);
                            }
                        }
                    }

                    $actualPrice = $oldPrice = array_key_exists('price', $readedInfos) ? (array_key_exists($localIndex, $readedInfos['price']) ? $readedInfos['price'][$localIndex] : null) : null;
                    if($readedInfos['uri'][$localIndex])
                    {
                        $newPrice = $ecomPriceSer->getPrice($readedInfos['uri'][$localIndex]);
                        if($newPrice != $actualPrice || $actualPrice === null)
                        {
                            if($newPrice)
                            {
                                $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . ' updating price from '.$actualPrice.' to '.$newPrice;
                                $this->errors[] = $errorMessage;
                                $this->output->writeln($errorMessage);
                                //TODO : effectuer la modification de prix
                            }else{
                                $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . ' no price detected, we remove link and price';
                                
                                $this->errors[] = $errorMessage;
                                $this->output->writeln($errorMessage);

                                $readedInfos['price'][$localIndex] = '';
                                $actualPrice = $oldPrice = null;
                            }
                        }
                    }

                    $errorMessage = null;
                    //On va ensuite recherche un meilleur prix
                    if(array_key_exists('price', $detectedInfos))
                    {
                        foreach ($detectedInfos['price'] as $provider => $proposedPrice)
                        {
                            if ($proposedPrice < $actualPrice || $actualPrice === null)
                            {
                                $actualPrice = $proposedPrice;
                                $newUrl = $detectedInfos['uri'][$provider];
                                $newUrl = $ecomAffiSer->getNewUrl($newUrl);

                                $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . ' we found a better price on : ' . $provider . ' - ' . $oldPrice . ' -> ' . $proposedPrice . ' - ' . $newUrl;
                            }
                        }
                    }
                    if($errorMessage)
                    {
                        $this->errors[] = $errorMessage;
                        $this->output->writeln($errorMessage);
                    }
                    
                    $url = $readedInfos['uri'][$localIndex];
                    $newUrl = $ecomAffiSer->getNewUrl($url);
                    if($newUrl != $url)
                    {
                        $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . ' setting affiliation '.$newUrl;
                        $this->errors[] = $errorMessage;
                        $this->output->writeln($errorMessage);
                    }
                    
                }
                $this->output->writeln("sheet " . $sheet['title'] . " done");
            }            
        }
        
        //$this->sendMailWithErrors();
        
        $this->output->writeln("DONE");
    }


    protected function getColumnsAssociation($category)
    {
        $associations = array();
        $associations['uri'] = array("Lien pour acheter");
        $associations['price'] = array("Prix (€)");
        $associations['brand'] = array("brand");
        $associations['model'] = array("model");
        $associations['ean'] = array("ean");
        $associations['energyClass'] = array("class");
        
        switch($category)
        {
            case 'aspirateur':                
                $associations['solDur'] = array("Sol dur (Hard floor)");
                $associations['tapis'] = array("Tapis (Carpet)");
                $associations['bruit'] = array("Bruit (dB)");
                $associations['filtration'] = array("Poussière (Dust)");
                $associations['sac'] = array("à sac");
                break;
            
            case 'machinealaver':
                $associations['annualConsumtion'] = array("Conso");
                //$associations['annualWaterConsumtion'] = array("L/annum");
                $associations['capacityKg'] = array("Capacité (kg)");
                $associations['qualiteEssorage'] = array("Efficacité d'essorage");
                $associations['bruitLavage'] = array("Bruit au lavage (dB)");
                $associations['bruitEssorage'] = array("Bruit à l'essorage (dB)");
                $associations['ouverture'] = array("ouverture");
                $associations['capacityKg'] = array("Capacité (kg)");
                break;

            case 'frigo':
                $associations['annualConsumtion'] = array("kWh/annum");
                $associations['volumeFridge'] = array("Contenance Fridge");
                $associations['volumeFreezer'] = array("Contenance Freezers", "Contenance Freezer");
                $associations['bruit'] = array("Bruit (dB)");
            //$associations[''] = array("Isolé");
            break;


            case 'frigo':
                $associations['annualConsumtion'] = array("kWh/annum");
                $associations['classicalConsumtion'] = array("Conso en chaleur normale");
                //$associations[''] = array("Consommation (kWh/cycle en chaleur tournante)");
                $associations['forcedConsumtion'] = array("Conso en chaleur tournante");
                $associations['volume'] = array("Contenance");
                $associations['fourType'] = array("Fours");
                $associations['fourNettoyage'] = array("Nettoyage");
                break;

            case 'lavevaisselle':
                $associations['annualConsumtion'] = array("Conso");
                $associations['annualWaterConsumtion'] = array("L/annum");
                $associations['qualiteSechage'] = array("Efficacité de séchage");
                $associations['bruit'] = array("Bruit (dB)");
                $associations['pose'] = array("Type");
                $associations['largeur'] = array("Taille (cm)");
                break;
                
            case 'hotte':
                $associations['annualConsumtion'] = array("Consommation (kWh/annum)");
                $associations['energyClassAspiration'] = array("Aspiration");
                $associations['energyClassLight'] = array("Lumière");
                $associations['energyClassGraisse'] = array("Filtration des graisses");
                $associations['bruit'] = array("Bruit (dB)");
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

        $this->addOption(
          'category',
          'c',
          InputOption::VALUE_OPTIONAL,
          'Document type',
          null
        );
        
    }


    protected function guessCategoryFromDoc($docId)
    {
        switch($docId)
        {
            case '1YTys0LNJt4OgHzDErSP_TTtzA49_9yxMhqILDph2_VA':
                return 'aspirateur';
            case '1PH9YEDFrLwb9S7ZdQq2M2vf9zPSNA1zbH6ODeQED7Qo':
                return 'frigo';
            case '1msHBIiyGUy42dVKl1ddXwJ6d7y_7WqZm9oht4VBPhf8':
                return 'machinealaver';
            case '1y55KqgfbhdBPJMmWPIiV_Qj7NW_PyB8mbNmIkg0hyH8':
                return 'four';
            case '1mKW8TpOcdkI5SZEUs6GKNvCyGf0_rLnZpfSjU-ByEeI':
                return 'lavevaisselle';
            case '1kPU2EW5A08yR1UXdwLn5sXytdoRxbIrPsQGpVJwtwSc':
                return 'pneu';
            case '1D3hHX0Eux_TQR9QIsNKMRLmW2fxfEJORwT7_2HKJ59E':
                return 'hotte';
            case '1WrQTglh9hLpYC2pL9Y9GYKNkMj28a1zOgw0JzEeKfzQ':
                return 'radiateur';
        }
        return null;
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