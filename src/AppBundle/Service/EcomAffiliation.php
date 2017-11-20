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
/*
            case 'www.darty.fr':
            case 'www.darty.com':
                return $this->getAffiliationForDarty($url);
*/
            case 'www.boulanger.fr':
            case 'www.boulanger.com':
                return $this->getAffiliationForBoulanger($url);

            case 'www.amazon.fr':
            case 'www.amazon.com':
                return $this->getAffiliationForAmazon($url);

            case 'www.rueducommerce.fr':
            case 'www.rueducommerce.com':
                return $this->getAffiliationForRueDuCommerce($url);
/*
            case 'www.but.fr':
            case 'www.but.com':
                return $this->getAffiliationForBut($url);

            case 'www.conforama.fr':
            case 'www.conforama.com':
                return $this->getAffiliationForConforama($url);
*/
            case 'www.priceminister.fr':
                return $this->getAffiliationForPriceminister($url);
/*
            case 'www.mistergooddeal.fr':
            case 'www.mistergooddeal.com':
                return $this->getAffiliationForMisterGoodDeal($url);
*/
            case 'www.webdistrib.fr':
            case 'www.webdistrib.com':
                return $this->getAffiliationForWebDistrib($url);

            case 'www.cdiscount.fr':
            case 'www.cdiscount.com':
                return $this->getAffiliationForCDiscount($url);

            case 'www.villatech.fr':
            case 'www.villatech.com':
                return $this->getAffiliationForVillatech($url);
                
            case 'www.magasins-privilege.fr':
                return $this->getAffiliationForMagazinPrivilege($url);
                
            case 'www.electrodepot.fr':
            case 'www.electrodepot.com':
                return $this->getAffiliationForElectroDepot($url);


            case 'www.priceminister.com':
            case 'www.priceminister.fr':
                return $this->getAffiliationForPriceminister($url);


            //SPECIAL
            case 'www.awin1.com':
                $sourceUrl = $this->getSourceUrlFrom($url, "p");
                return $this->getNewUrl($sourceUrl);
            case 'track.effiliation.com':
                $sourceUrl = $this->getSourceUrlFrom($url, "url");
                return $this->getNewUrl($sourceUrl);

            case 'action.metaffiliation.com':
                $sourceUrl = $this->getSourceUrlFrom($url, "redir");
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
            case 'clk.tradedoubler.com':
                $sourceUrl = $this->getSourceUrlFrom($url, "url");
                return $sourceUrl;
        }
        return $url;
    }

    protected function getAffiliationForBoulanger($url)
    {
        return $this->getEffiliationLink($url, 16300285, 'xtor=AL-6875-[**typeaffilie**]-[1395069625]-[deeplink]');
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

    protected function getAffiliationForMisterGoodDeal($url)
    {
        return $this->getTradeDoublerLink($url, '?p=105837&a=2950396&g=21768610');
    }

    protected function getAffiliationForWebDistrib($url)
    {
        return $this->getEffiliationLink($url, 18794117, 'utm_source=Effiliation&utm_medium=1395069625&site=Effiliation&utm_medium=deeplink&utm_campaign=EffiliationWebd');
    }

    protected function getAffiliationForCDiscount($url)
    {
        return $this->getAffiliateWindowsLink($url, 6948);
    }

    protected function getAffiliationForElectroDepot($url)
    {
        return $this->getEffiliationLink($url, 18080963);
    }


    protected function getAffiliationForVillatech($url)
    {
        return $this->getMetaffiliationLink($url, "P41E6F56C63D151");
    }

    protected function getAffiliationForMagazinPrivilege($url)
    {
        return $this->getMetaffiliationLink($url, "P43DFF56C63D131");
    }

    protected function getEffiliationLink($url, $id=16300285, $suffix = null)
    {
        if($suffix)
        {
            $url = trim($url, '?&');
            $url .= (strpos($url, '?') !== false ? '&' : '?').$suffix;
        }
        return "http://track.effiliation.com/servlet/effi.redir?id_compteur=".$id."&url=".urlencode($url);
    }

    protected function getTradeDoublerLink($url, $id, $suffix = null)
    {
        if($suffix)
        {
            $url = trim($url, '?&');
            $url .= (strpos($url, '?') !== false ? '&' : '?').$suffix;
        }
        return "http://clk.tradedoubler.com/click".$id."&url=".urlencode($url);
    }


    protected function getAffiliateWindowsLink($url, $id = 6901)
    {
        return "http://www.awin1.com/cread.php?awinmid=".$id."&awinaffid=311895&clickref=&p=".urlencode($url);
    }

    protected function getMetaffiliationLink($url, $id = "P41E6F56C63D151")
    {
        return "http://action.metaffiliation.com/trk.php?mclic=".$id."&redir=".urlencode($url);
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
        $url = array_key_exists($paramName, $params) ? $params[$paramName] : null;
        if(empty($url))
        {
            return $url;
        }

        $url = str_replace('xtor=AL-6875-[**typeaffilie**]-[1395069625]-[deeplink]', '', $url);
        $url = str_replace('utm_source=Effiliation&utm_medium=1395069625&site=Effiliation&utm_medium=deeplink&utm_campaign=EffiliationWebd', '', $url);

        return $url;
    }
}
