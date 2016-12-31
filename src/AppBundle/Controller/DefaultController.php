<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
          'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR,
        ]);
    }

    /**
     * @Route("/updateprice", name="updateprice")
     */
    public function updatepriceAction(Request $request)
    {
        // replace this example code with whatever you need
        $root = $this->get('kernel')->getRootDir();
        $command = 'bash '.escapeshellarg($root.'/../bin/flo.sh');
        $command = 'cd '.escapeshellarg($root.'/..').' && timeout 3600 '.$command.' 1>> /tmp/test 2>&1 &';
        var_dump($command);
        shell_exec($command);
        return $this->redirect($this->generateUrl("homepage"));
    }

    /**
     * @Route("/checkinfo", name="checkinfo")
     */
    public function checkInfoAction(Request $request)
    {
        // replace this example code with whatever you need
        $root = $this->get('kernel')->getRootDir();
        $docId = $request->get('doc');
        $category = $request->get('category');
        $command = 'php bin/console ecomscraper:sheet:checkinfo --doc '.escapeshellarg($docId).' --category '.escapeshellarg($category);
        $command = 'cd '.escapeshellarg($root.'/..').' && timeout 3600 '.$command.' 1>> /tmp/test 2>&1 &';
        //var_dump($command);
        shell_exec($command);
        return $this->redirect($this->generateUrl("homepage"));
    }
    
    /**
    * @Route("/findinfo", name="findinfo")
    */
    public function findInfoAction(Request $request)
    {
        // replace this example code with whatever you need
        $root = $this->get('kernel')->getRootDir();
        $category = $request->get('category');
        $ean = $request->get('ean');

        $ecomInfoSer = $this->get('ecom.info');
        $infos = $ecomInfoSer->getInfos($ean, $category);
        
        $output = '';
        if(is_array($infos))
        {
            foreach ($infos as $infoName => $infoValues)
            {
                if(empty($infoName))
                {
                    continue;
                }
                $output.="\n<br /><br /><strong>".$infoName . '</strong> : ' ;
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
                    $output.="\n".'<br />&nbsp;&nbsp;<span class="agree">' . count($infoValues).' sites : '.$lastValue.'</span>';
                }else{
                    foreach($infoValues as $provider=>$providerValue)
                    {
                        $output.="\n".'<br />&nbsp;&nbsp;<span class="notagree">'.$provider .' : '.$providerValue.'</span>';
                    }
                }
            }
        }
        
        return $this->render('default/index.html.twig', [
          'output' => $output,
          'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR,
        ]);
    }

}
