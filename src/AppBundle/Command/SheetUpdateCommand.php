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

        $letters='abcdefghijklmnopqrstuvwxyz';
        $columnForPrice=null;
        $columnForUrl=null;
        $columnForDate=null;
        for($iColumn=0;$iColumn<24;$iColumn++)
        {
            $letter = $letters{$iColumn};
            $value = $ecomSheet->getSheetValue($docId, $letter."1");
            if($value == "Prix (€)")
            {
                $columnForPrice = $letter;
            }elseif($value == "Lien pour acheter")
            {
                $columnForUrl = $letter;
            }elseif($value == "Date MAJ Prix")
            {
                $columnForDate = $letter;
            }
            if($columnForUrl && $columnForPrice && $columnForDate)
            {
                break;
            }
        }

        if(empty($columnForPrice))
        {
            throw new \Exception("Unable to find price column");
        }
        if(empty($columnForUrl))
        {
            throw new \Exception("Unable to find url column");
        }
        
        $maxLineNumber = 100;
        $nbErrorLeft=10;
        for($lineNumber=2;$lineNumber<=$maxLineNumber;$lineNumber++)
        {
            $actualPrice = $ecomSheet->getSheetValue($docId, $columnForPrice.$lineNumber);
            $actualPrice = $ecomPriceSer->formatPrice($actualPrice);
            $url = $ecomSheet->getSheetValue($docId, $columnForUrl.$lineNumber);
            if(empty($url))
            {
                $nbErrorLeft--;
                if($nbErrorLeft>=0)
                {
                    continue;
                }else
                {
                    break;
                }
            }
            $newPrice = $ecomPriceSer->getPrice($url);
            if(empty($newPrice))
            {
                $this->output->writeln('<error>No price detected for '.$url.'</error>');
            }else{
                if($newPrice == $actualPrice)
                {
                    $this->output->writeln('' . $url . ' ' . $actualPrice.' - not modified');
                    if($columnForDate)
                    {
                        $ecomSheet->setSheetValue($docId, $columnForDate.$lineNumber, date('Y-m-d H:i:s', $startTime));
                    }
                }else{
                    $evol = (round((100*($actualPrice - $newPrice) / $actualPrice)));
                    $this->output->writeln('' . $url . ' ' . $actualPrice.' => '.$newPrice.' ('.$evol.'%)');
                    if($evol < 20)
                    {
                        $ecomSheet->setSheetValue($docId, $columnForPrice.$lineNumber, $newPrice);
                        if($columnForDate)
                        {
                            $ecomSheet->setSheetValue($docId, $columnForDate.$lineNumber, date('Y-m-d H:i:s', $startTime));
                        }
                    }
                }
            }
            //var_dump($newPrice);
        }
        
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
        
    }
}