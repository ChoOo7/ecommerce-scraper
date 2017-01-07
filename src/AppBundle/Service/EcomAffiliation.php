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


class EcomAffiliation
{


    /**
     * {@inheritDoc}
     */
    public function getNewUrl($url)
    {
        $hostname = parse_url($url, PHP_URL_HOST);
        $hostname = strtolower($hostname);
        switch($hostname)
        {
            case 'www.boulanger.fr':
            case 'www.boulanger.com':
                return $this->getAffiliationForBoulanger($url);

            case 'www.rueducommerce.fr':
            case 'www.rueducommerce.com':
                return $this->getAffiliationForRueDuCommerce($url);

            case 'www.but.fr':
            case 'www.but.com':
                return $this->getAffiliationForBut($url);
            
            case 'www.conforama.fr':
            case 'www.conforama.com':
                return $this->getAffiliationForConforama($url);

            case 'www.amazon.fr':
            case 'www.amazon.com':
                return $this->getAffiliationForAmazon($url);
        }
        return $url;
    }

    protected function getAffiliationForBoulanger($url)
    {
        return $this->getEffiliationLink($url);
    }

    protected function getAffiliationForRueDuCommerce($url)
    {
        return $this->getEffiliationLink($url);
    }

    protected function getAffiliationForBut($url)
    {
        return $this->getEffiliationLink($url);
    }

    protected function getAffiliationForConforama($url)
    {
        return $this->getEffiliationLink($url);
    }

    protected function getEffiliationLink($url)
    {
        return "http://track.effiliation.com/servlet/effi.redir?id_compteur=16300285&url=".urlencode($url);
    }

    protected function getAffiliationForAmazon($url)
    {
        $currentTag = null;
        $actualQueryString = parse_url($url, PHP_URL_QUERY);
        $actualQueryStringParameters = array();
        parse_str($actualQueryString, $actualQueryStringParameters);
        $actualQueryStringParameters['tag'] = 'labellenergie-21';
        
        $tmp = explode('?', $url);
        
        return $tmp[0].'?'.http_build_query($actualQueryStringParameters, null, '&');
    }
}