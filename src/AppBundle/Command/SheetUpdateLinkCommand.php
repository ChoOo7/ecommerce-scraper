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


class SheetUpdateLinkCommand extends ContainerAwareCommand
{

    

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $startTime = time();
        $this->input = $input;
        $this->output = $output;
        //$ecomPriceSer = $this->getContainer()->get('ecom.price');
        $ecomAffiSer = $this->getContainer()->get('ecom.affiliation');
        $ecomSheet = $this->getContainer()->get('ecom.sheet_updater');

        $docId = $this->input->getOption('doc');

        $letters='abcdefghijklmnopqrstuvwxyz';
        $columnForUrl=null;
        for($iColumn=0;$iColumn<24;$iColumn++)
        {
            $letter = $letters{$iColumn};
            $value = $ecomSheet->getSheetValue($docId, $letter."1");
            if($value == "Lien pour acheter")
            {
                $columnForUrl = $letter;
            }
            if($columnForUrl)
            {
                break;
            }
        }
        if(empty($columnForUrl))
        {
            throw new \Exception("Unable to find url column");
        }
        
        $maxLineNumber = 100;
        $nbErrorLeft=10;
        for($lineNumber=2;$lineNumber<=$maxLineNumber;$lineNumber++)
        {
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
            $newUrl = $ecomAffiSer->getNewUrl($url);
            if($newUrl != $url)
            {
                $this->output->writeln('' . $url . ' => ' . $newUrl.'');
                $ecomSheet->setSheetValue($docId, $columnForUrl.$lineNumber, $newUrl);
            }
        }
        
        $this->output->writeln("DONE");
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('ecomscraper:sheet:updatelink');
        $this->setDescription('Update links on sheets');


        $this->addOption(
          'doc',
          'd',
          InputOption::VALUE_REQUIRED,
          'Document segment',
          null
        );
        
    }
}