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


class GetPriceCommand extends ContainerAwareCommand
{


    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $ecomPriceSer = $this->getContainer()->get('ecom.price');

        $url = $this->input->getOption('url');
        $price = $ecomPriceSer->getPrice($url);
        
        $this->output->writeln($price);
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('ecomscraper:getprice');
        $this->setDescription('Get a price from an URL');

        $this->addOption(
          'url',
          'u',
          InputOption::VALUE_REQUIRED, 
          'Url',
          null
        );
    }
}