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
        if(empty($url))
        {
            return $url;
        }
        $hostname = parse_url($url, PHP_URL_HOST);
        $hostname = strtolower($hostname);
        switch($hostname)
        {
            case 'www.darty.fr':
            case 'www.darty.com':
                return $this->getAffiliationForDarty($url);
            
            case 'www.boulanger.fr':
            case 'www.boulanger.com':
                return $this->getAffiliationForBoulanger($url);

            case 'www.amazon.fr':
            case 'www.amazon.com':
                return $this->getAffiliationForAmazon($url);

            case 'www.rueducommerce.fr':
            case 'www.rueducommerce.com':
                return $this->getAffiliationForRueDuCommerce($url);

            case 'www.but.fr':
            case 'www.but.com':
                return $this->getAffiliationForBut($url);

            case 'www.conforama.fr':
            case 'www.conforama.com':
                return $this->getAffiliationForConforama($url);

            case 'www.priceminister.fr':
                return $this->getAffiliationForPriceminister($url);

            case 'www.webdistrib.fr':
            case 'www.webdistrib.com':
                return $this->getAffiliationForWebDistrib($url);


            case 'www.electrodepot.fr':
            case 'www.electrodepot.com':
                return $this->getAffiliationForElectroDepot($url);
            
            
            //SPECIAL
            case 'www.awin1.com':
                $sourceUrl = $this->getSourceUrlFrom($url, "p");
                return $this->getNewUrl($sourceUrl);
            case 'track.effiliation.com':
                $sourceUrl = $this->getSourceUrlFrom($url, "url");
                return $this->getNewUrl($sourceUrl);
        }
        return $url;
    }
    
    public function getOriginalUrl($url)
    {
        if(empty($url))
        {
            return $url;
        }
        $hostname = parse_url($url, PHP_URL_HOST);
        $hostname = strtolower($hostname);
        switch($hostname)
        {

            //SPECIAL
            case 'www.awin1.com':
                $sourceUrl = $this->getSourceUrlFrom($url, "p");
                return $sourceUrl;
            case 'track.effiliation.com':
                $sourceUrl = $this->getSourceUrlFrom($url, "url");
                return $sourceUrl;
        }
        return $url;
    }

    protected function getAffiliationForBoulanger($url)
    {
        return $this->getEffiliationLink($url, 16300285);
    }


    protected function getAffiliationForDarty($url)
    {
        return $this->getAffiliateWindowsLink($url);
    }

    protected function getAffiliationForRueDuCommerce($url)
    {
        return $this->getAffiliateWindowsLink($url);
    }

    protected function getAffiliationForBut($url)
    {
        return $this->getAffiliateWindowsLink($url);
    }

    protected function getAffiliationForConforama($url)
    {
        return $this->getAffiliateWindowsLink($url);
    }
    protected function getAffiliationForPriceminister($url)
    {
        return $this->getEffiliationLink($url, 18233737);
    }
    protected function getAffiliationForWebDistrib($url)
    {
        return $this->getEffiliationLink($url, 18794117);
    }

    protected function getAffiliationForElectroDepot($url)
    {
        return $this->getEffiliationLink($url, 1808096);
    }
    
    protected function getEffiliationLink($url, $id=16300285)
    {
        return "http://track.effiliation.com/servlet/effi.redir?id_compteur=".$id."&url=".urlencode($url);
    }

    protected function getAffiliateWindowsLink($url)
    {
        return "http://www.awin1.com/cread.php?awinmid=6901&awinaffid=311895&clickref=&p=".urlencode($url);
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
    
    protected function getSourceUrlFrom($url, $paramName)
    {
        $params = array();
        parse_str($url, $params);
        return array_key_exists($paramName, $params) ? $params[$paramName] : null;
    }
}