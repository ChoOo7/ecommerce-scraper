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

use AppBundle\Exception\Expirated;
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

        $doRemovePrice = false;

        $docId = $this->input->getOption('doc');
        $category = $this->input->getOption('category');
        if(empty($category))
        {
            $category = $this->guessCategoryFromDoc($docId);
        }
        $checkInfo = $this->input->getOption('checkinfo') == "true";
        
        $sheets = $ecomSheet->getSheets($docId);
        foreach($sheets as $sheet)
        {
            $this->output->writeln("starting sheet ".$sheet['title']."");
            
            if(stripos($sheet['title'], 'pivot') !== false)
            {
                $this->output->writeln("sheet pivot ignored");
                continue;
            }
            
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
                            $values[] = empty($v) ? null : $v[0];
                        }
                    }
                    $readedInfos[$k] = $values;
                }

                $packetIndex++;
                if( ! is_array($eans))
                {
                    $continueScanning = false;
                    break;                    
                }
                foreach ($eans as $k => $data)
                {
                    $eans[$k] = array_key_exists(0, $data) ? $data[0] : null;
                }
                if (count(array_filter($eans)) == 0)
                {
                    $continueScanning = false;
                }


                foreach ($eans as $localIndex => $ean)
                {
                    if(empty($ean))
                    {
                        //sans ean, on ne fait rien !
                        continue;
                    }
                    $globalLineNumber = $lineNumber + $localIndex;
                    $detectedInfos = $ecomInfoSer->getInfos($ean, $category);
                    
                    $linksInfos = 'Other websites urls : ';
                    if(array_key_exists('uri', $detectedInfos))
                    {
                        foreach ($detectedInfos['uri'] as $provider => $_uri)
                        {
                            $linksInfos .= ' <a href="' . $_uri . '">' . $provider . '</a>';
                        }
                    }
                    
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
                            if($columnName == 'price' || $columnName == 'uri')
                            {
                                continue;
                            }
                            //On va alors remplir le fichier excel
                            $newValue = null;
                            $theyAgree = true;
                            foreach ($detectedValues as $provider => $value)
                            {
                                if($newValue == null)
                                {
                                    $newValue = $value;
                                }elseif($newValue != $value && strtolower($newValue) != strtolower($value))
                                {
                                    $theyAgree = false;
                                }                                
                            }
                            if($newValue)
                            {
                                if( ! $theyAgree)
                                {
                                    $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . $columnName . ' is empty, we can fill it with : ';
                                    foreach($detectedValues as $provider => $value)
                                    {
                                        $link = array_key_exists($localIndex, $detectedInfos['uri']) ? $detectedInfos['uri'][$provider] : null;
                                        
                                        $formatedValue = $value;
                                        if(in_array($columnName, array('image_url', 'image_energy_url')))
                                        {
                                            $formatedValue = '<a href="'.$value.'"><img src="'.$value.'" width="100" alt="view image" /></a>';
                                        }

                                        $column = $columnIndexes[$columnName];
                                        $updateUrl = $this->generateSetValueUrl($docId, $sheet['title'], array($column.$globalLineNumber=>$value));
                                        
                                        $errorMessage.="\n"."  - ".$provider.' - '.$formatedValue .' <a href="'.$updateUrl.'">CHOOSE IT</a>'.($link ?  ' ( <a href="'.$link.'">view website</a>" )' : '');
                                    }
                                    $this->errors[] = $errorMessage;
                                    $this->output->writeln($errorMessage);
                                }else{
                                    $formatedValue = $newValue;
                                    if(in_array($columnName, array('image_url', 'image_energy_url')))
                                    {
                                        $formatedValue = '<a href="'.$newValue.'"><img src="'.$newValue.'" width="100" alt="view image" /></a>';
                                    }
                                    
                                    $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . $columnName . ' is empty, we fill it with : '.$formatedValue. ' ('.count($detectedValues).' concordant infos)';
                                    $errorMessage .= ' <br /> '.$linksInfos;
                                    $this->errors[] = $errorMessage;
                                    
                                    $this->output->writeln($errorMessage);
                                    
                                    $column = $columnIndexes[$columnName];
                                    $ecomSheet->setSheetValue($docId, $column.$globalLineNumber, $newValue, $sheet['title']);
                                    
                                    $column = $columnIndexes['lastUpdateDate'];
                                    $ecomSheet->setSheetValue($docId, $column.$globalLineNumber, date('Y-m-d H:i:s'), $sheet['title']);
                                }                                                                
                            }                            
                        }else
                        {
                            //We check actual values
                            if($checkInfo)
                            {
                                $newValue = null;
                                $theyAgree = true;
                                foreach ($detectedValues as $provider => $value)
                                {
                                    if($newValue == null)
                                    {
                                        $newValue = $value;
                                    }elseif($newValue != $value && strtolower($newValue) != strtolower($value))
                                    {
                                        $theyAgree = false;
                                    }
                                }
                                if( ! $theyAgree || $newValue != $actualValue)
                                {
                                    $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . $columnName . ' : actualValue : ' . $actualValue . '. Detected value : ';
                                    foreach($detectedValues as $provider => $value)
                                    {
                                        //$link = $readedInfos['uri'][$localIndex];
                                        $link = array_key_exists($localIndex, $detectedInfos['uri']) ? $detectedInfos['uri'][$provider] : null;

                                        $column = $columnIndexes[$columnName];
                                        $updateUrl = $this->generateSetValueUrl($docId, $sheet['title'], array($column.$globalLineNumber=>$value));
                                        
                                        $errorMessage.="\n"."  - ".$provider.' - '.$value .' <a href="'.$updateUrl.'">CHOOSE IT</a> ( <a href="'.$link.'>view website</a> )';
                                    }
                                }
                            }
                        }
                    }

                    $expirated = false;
                    $actualPrice = $oldPrice = array_key_exists('price', $readedInfos) ? (array_key_exists($localIndex, $readedInfos['price']) ? $readedInfos['price'][$localIndex] : null) : null;
                    if(array_key_exists('uri', $readedInfos) && array_key_exists($localIndex, $readedInfos['uri']) && $readedInfos['uri'][$localIndex])
                    {                        
                        $newPrice = null;
                        try
                        {
                            $newPrice = $ecomPriceSer->getPrice($readedInfos['uri'][$localIndex]);
                        }
                        catch(Expirated $e)
                        {
                            $expirated = true;
                            $actualPrice = null;
                        }
                        if($newPrice != $actualPrice || $actualPrice === null)
                        {
                            if($newPrice)
                            {
                                $variationIsOk = $actualPrice == 0 || (abs(($newPrice - $actualPrice) / $actualPrice) < 0.3);
                                if($variationIsOk)
                                {
                                    $hostname = parse_url($readedInfos['uri'][$localIndex], PHP_URL_HOST);
                                    $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . ' updating price from ' . $actualPrice . ' to ' . $newPrice . ' on url <a href="' . $readedInfos['uri'][$localIndex] . '">' . $hostname . '</a>';
                                    $this->errors[] = $errorMessage;
                                    $this->output->writeln($errorMessage);

                                    $column = $columnIndexes['price'];
                                    $ecomSheet->setSheetValue($docId, $column . $globalLineNumber, $newPrice, $sheet['title']);
                                    $column = $columnIndexes['lastUpdateDate'];
                                    $ecomSheet->setSheetValue($docId, $column . $globalLineNumber, date('d/m/Y H:i:s'), $sheet['title']);
                                }else{
                                    $hostname = parse_url($readedInfos['uri'][$localIndex], PHP_URL_HOST);
                                    $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . ' price from ' . $actualPrice . ' to ' . $newPrice . ' on url <a href="' . $readedInfos['uri'][$localIndex] . '">' . $hostname . '</a>';

                                    $columnPrice = $columnIndexes['price'];
                                    $columnDate = $columnIndexes['lastUpdateDate'];
                                    $updateUrl = $this->generateSetValueUrl($docId, $sheet['title'], array($columnPrice.$globalLineNumber=>$newPrice, $columnDate.$globalLineNumber=>date('d/m/Y H:i:s')));
                                    $errorMessage .= '<br /><a href="'.$updateUrl.'">confirm and set new price</a>';
                                    
                                    $this->errors[] = $errorMessage;
                                    $this->output->writeln($errorMessage);
                                }
                            }else{
                                if($doRemovePrice || $expirated)
                                {
                                    $_uri = $readedInfos['uri'][$localIndex];
                                    $hostname = parse_url($_uri, PHP_URL_HOST);
                                    $errorMessage = "";
                                    if($expirated)
                                    {
                                        $errorMessage .= 'EXPIRATED : ';
                                    }
                                    $errorMessage .= 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . ' no price detected on url <a href="'.$_uri.'">'.$hostname.'</a>, we remove link and price. (google sheet price : '.((string)$actualPrice).')';
                                    $errorMessage .= ' <br /> '.$linksInfos;

                                    $this->errors[] = $errorMessage;
                                    $this->output->writeln($errorMessage);

                                    $readedInfos['price'][$localIndex] = '';
                                    $actualPrice = $oldPrice = null;

                                    $column = $columnIndexes['price'];
                                    $ecomSheet->setSheetValue($docId, $column.$globalLineNumber, '', $sheet['title']);
                                    $column = $columnIndexes['uri'];
                                    $ecomSheet->setSheetValue($docId, $column.$globalLineNumber, '', $sheet['title']);
                                    $column = $columnIndexes['lastUpdateDate'];
                                    $ecomSheet->setSheetValue($docId, $column.$globalLineNumber, date('Y-m-d H:i:s'), $sheet['title']);                                    
                                }else{
                                    $hostname = parse_url($readedInfos['uri'][$localIndex], PHP_URL_HOST);
                                    $errorMessage = "";
                                    if($expirated)
                                    {
                                        $errorMessage .= 'EXPIRATED : ';
                                    }
                                    $errorMessage .= 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . ' no price detected on url <a href="'.$readedInfos['uri'][$localIndex].'">'.$hostname.'</a> (google sheet price : '.((string)$actualPrice).')';
                                    $errorMessage .= ' <br /> '.$linksInfos;

                                    $columnPrice = $columnIndexes['price'];
                                    $columnUri = $columnIndexes['uri'];
                                    
                                    $updateUrl = $this->generateSetValueUrl($docId, $sheet['title'], array($columnPrice.$globalLineNumber=>'', $columnUri.$globalLineNumber=>''));
                                    $errorMessage .= '<br /><a href="'.$updateUrl.'">confirm and remove price and link</a>';

                                    $this->errors[] = $errorMessage;
                                    $this->output->writeln($errorMessage);
                                }
                            }
                        }else{
                            //le prix ne change pas
                            $column = $columnIndexes['lastUpdateDate'];
                            $ecomSheet->setSheetValue($docId, $column.$globalLineNumber, date('Y-m-d H:i:s'), $sheet['title']);
                        }
                    }

                    $errorMessage = null;
                    //On va ensuite recherche un meilleur prix
                    if(array_key_exists('price', $detectedInfos) && ! (array_key_exists('expirated', $detectedInfos) && $detectedInfos['expirated']))
                    {
                        foreach ($detectedInfos['price'] as $provider => $proposedPrice)
                        {
                            if ($proposedPrice > 0 && ($proposedPrice < $actualPrice || $actualPrice === null || $expirated))
                            {
                                $newPrice = $proposedPrice;
                                $variationIsOk = $expirated || $actualPrice == 0 || (abs(($newPrice - $actualPrice) / $actualPrice) < 0.3);

                                $newUrl = $detectedInfos['uri'][$provider];
                                $newUrl = $ecomAffiSer->getNewUrl($newUrl);

                                $brand = array_key_exists('brand', $readedInfos) && array_key_exists($localIndex, $readedInfos['brand']) ? $readedInfos['brand'][$localIndex] : null;
                                $model = array_key_exists('model', $readedInfos) && array_key_exists($localIndex, $readedInfos['model']) ? $readedInfos['model'][$localIndex] : null;

                                $brand = (string)$brand;
                                $model = (string)$model;

                                $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . $brand . ' - ' . $model . ' -  we found a better price on : ' . $provider . ' - ' . $oldPrice . ' -> ' . $proposedPrice . ' - <a href="' . $newUrl . '">view website</a>';
                                
                                
                                if($variationIsOk)
                                {
                                    $errorMessage .= '=> document auto updated';
                                    $actualPrice = $proposedPrice;

                                    $column = $columnIndexes['price'];
                                    $ecomSheet->setSheetValue($docId, $column . $globalLineNumber, $proposedPrice, $sheet['title']);

                                    $column = $columnIndexes['uri'];
                                    $ecomSheet->setSheetValue($docId, $column . $globalLineNumber, $newUrl, $sheet['title']);
                                }else{
                                    $columnPrice = $columnIndexes['price'];
                                    $columnUri = $columnIndexes['uri'];
                                    
                                    $updateUrl = $this->generateSetValueUrl($docId, $sheet['title'], array($columnPrice.$globalLineNumber=>$proposedPrice, $columnUri.$globalLineNumber=>$newUrl));
                                    $errorMessage .= '<br /><a href="'.$updateUrl.'">confirm and set price and link</a>';
                                }
                                $errorMessage .= ' <br /> ' . $linksInfos;
                            }
                        }
                    }
                    if($errorMessage)
                    {
                        $this->errors[] = $errorMessage;
                        $this->output->writeln($errorMessage);
                    }
                    
                    $url = array_key_exists($localIndex, $readedInfos['uri']) ? $readedInfos['uri'][$localIndex] : null;
                    if($url)
                    {
                        $newUrl = $ecomAffiSer->getNewUrl($url);
                        if ($newUrl != $url)
                        {
                            $errorMessage = 'Onglet ' . $sheet['title'] . ' Ligne ' . $globalLineNumber . ' - ' . ' setting affiliation tag to link ' . $newUrl;
                            $this->errors[] = $errorMessage;
                            $this->output->writeln($errorMessage);
                            
                            $column = $columnIndexes['uri'];
                            $ecomSheet->setSheetValue($docId, $column.$globalLineNumber, $newUrl, $sheet['title']);
                        }
                    }                    
                }
                $this->output->writeln("sheet " . $sheet['title'] . " done");
            }            
        }
        
        $this->sendMailWithErrors($category);
        
        $this->output->writeln("DONE");
    }
    
    protected function generateSetValueUrl($docId, $sheet, $valuesToUpdate)
    {
        $parameters = array();
        $parameters['doc'] = $docId;
        $parameters['sheet'] = $sheet;
        $parameters['valuesToUpdate'] = $valuesToUpdate;
        $isRasp = ! file_exists('/1to/');
        $hostname = $isRasp ? 'ecom-scrapper.home.chooo7.com' : 'ecom.local';
        $url = 'http://'.$hostname.'/setSheetValue?'.http_build_query($parameters, null, '&');
        return $url;
    }


    protected function getColumnsAssociation($category)
    {
        $associations = array();
        $associations['uri'] = array("Lien pour acheter");
        $associations['price'] = array("Prix (€)", "Le meilleur prix");
        $associations['brand'] = array("brand");
        $associations['model'] = array("model");
        $associations['ean'] = array("ean");
        $associations['image_url'] = array("image_url");
        $associations['image_energy_url'] = array("image_energy_url");
        $associations['energyClass'] = array("class");
        $associations['lastUpdateDate'] = array("Date MAJ Prix");
        
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
                $associations['annualWaterConsumtion'] = array("L/annum");
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

        $this->addOption(
          'checkinfo',
          '',
          InputOption::VALUE_OPTIONAL,
          'Check all informations. Default false',
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
    
    protected function sendMailWithErrors($category)
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

        $mailFileName = date('Y-m-m-H:i:s').'-'.uniqid('a').'.html';
        $isRasp = ! file_exists('/1to/');
        $hostname = $isRasp ? 'ecom-scrapper.home.chooo7.com' : 'ecom.local';
        $onlineEmailLink = 'http://'.$hostname.'/mails/'.$mailFileName;
        

        $subject = "Import result ";
        if($category)
        {
            $subject.= ' on '.$category;
        }
        $body = "<h1>Actions : </h1>";
        $body .= "\n".'<div>';
        $body .= "\n".'<p>Document : <a href="'.$documentLink.'">'.$documentLink.'</a></p>';
        $body .= "\n".'<p>View this email online : <a href="'.$onlineEmailLink.'">'.$onlineEmailLink.'</a></p>';
        $body .= "\n".'</div>';
        $body .= "\n".'<div>';
        $body .= "\n".'<ul>';
        foreach($this->errors as $error)
        {
            $body .= "\n".'<li>'.nl2br($error).'</li>';
        }
        $body .= "\n".'</ul>';
        $body .= "\n".'</div>';


        $mailHtmlContent = '<html><head></head><body>'.$body.'</body></html>';

        $root = $this->getContainer()->get('kernel')->getRootDir().'/../';
        //$root = $this->kernel->getRootDir();
        $dir = $root.'/web/mail/';
        file_put_contents($dir.$mailFileName, $mailHtmlContent);
        
        $email = new \SendGrid\Email();
        foreach($tos as $to)
        {
            $email->addTo($to)->setFrom($from)->setSubject($subject)->setHtml($body);
        }
        $resp = $this->sendGrid->send($email);
    }
}