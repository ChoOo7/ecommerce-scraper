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


class GetInfoCommand extends ContainerAwareCommand
{


    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $ecomInfoSer = $this->getContainer()->get('ecom.info');

        $ean = $this->input->getOption('ean');
        $category = $this->input->getOption('category');
        $infos = $ecomInfoSer->getInfos($ean, $category);
        if(is_array($infos))
        {
            foreach ($infos as $infoName => $infoValues)
            {
                $this->output->writeln($infoName . ' : ' );
                $theyAgree = true;
                $lastValue = null;
                foreach($infoValues as $provider=>$providerValue)
                {
                    if($lastValue === null)
                    {
                        $lastValue = $providerValue;
                    }elseif($providerValue != $lastValue && strtolower($providerValue) != strtolower($lastValue))
                    {
                        $theyAgree = false;
                        break;
                    }
                }
                if($theyAgree)
                {
                    $this->output->writeln("\t" . count($infoValues).' sites : '.$lastValue);
                }else{
                    foreach($infoValues as $provider=>$providerValue)
                    {
                        $this->output->writeln("\t" . $provider .' : '.$providerValue);
                    }
                }
            }
        }
        if(empty($infos))
        {
            $this->output->writeln('no infos');
        }
        $this->output->writeln('done');
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('ecomscraper:getinfo');
        $this->setDescription('Get a price from an URL');

        $this->addOption(
          'ean',
          '',
          InputOption::VALUE_REQUIRED,
          'EAN of product',
          null
        );
        
        $this->addOption(
          'category',
          'c',
          InputOption::VALUE_REQUIRED,
          'Category of product',
          null
        );
    }
}