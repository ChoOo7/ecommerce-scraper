<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    
    protected function getDocs()
    {
        $docs = array(
          '1YTys0LNJt4OgHzDErSP_TTtzA49_9yxMhqILDph2_VA'=>'aspirateur',
          '1PH9YEDFrLwb9S7ZdQq2M2vf9zPSNA1zbH6ODeQED7Qo'=>'frigo',
          '1msHBIiyGUy42dVKl1ddXwJ6d7y_7WqZm9oht4VBPhf8'=>'machinealaver',
          '1y55KqgfbhdBPJMmWPIiV_Qj7NW_PyB8mbNmIkg0hyH8'=>'four',
          '1mKW8TpOcdkI5SZEUs6GKNvCyGf0_rLnZpfSjU-ByEeI'=>'lavevaisselle',
          '1kPU2EW5A08yR1UXdwLn5sXytdoRxbIrPsQGpVJwtwSc'=>'pneu',
          '1D3hHX0Eux_TQR9QIsNKMRLmW2fxfEJORwT7_2HKJ59E'=>'hotte',
          '1WrQTglh9hLpYC2pL9Y9GYKNkMj28a1zOgw0JzEeKfzQ'=>'radiateur'
        );
        return $docs;
    }
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $docs = $this->getDocs();
        
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
          'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR,
          'docs'=>$docs
        ]);
    }

    /**
     * @Route("/updateinfo", name="updateinfo")
     */
    public function updateinfoAction(Request $request)
    {
        // replace this example code with whatever you need
        $root = $this->get('kernel')->getRootDir();
        $docId = $request->get('doc');
        $command = null;
        if($docId)
        {
            $command = 'php bin/console ecomscraper:sheet:update --doc '.escapeshellarg($docId);
        }else{
            $command = 'bash '.escapeshellarg($root.'/../bin/flo.sh');
        }
        $command = 'cd '.escapeshellarg($root.'/..').' && timeout 3600 '.$command.' 1>> /tmp/test 2>&1 &';
        var_dump($command);
        shell_exec($command);
        return $this->redirect($this->generateUrl("homepage"));
    }

    /**
     * @Route("/updatelink", name="updatelink")
     */
    public function updatelinkAction(Request $request)
    {
        // replace this example code with whatever you need
        $root = $this->get('kernel')->getRootDir();
        $docId = $request->get('doc');
        $command = null;
        if($docId)
        {
            $command = 'php bin/console ecomscraper:sheet:updatelink --doc '.escapeshellarg($docId);
        }else{
            $command = 'bash '.escapeshellarg($root.'/../bin/flo.sh');
        }
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
        $command = 'php bin/console ecomscraper:sheet:update --checkinfo true --doc '.escapeshellarg($docId);
        $command = 'cd '.escapeshellarg($root.'/..').' && timeout 3600 '.$command.' 1>> /tmp/test 2>&1 &';
        //var_dump($command);
        shell_exec($command);
        return $this->redirect($this->generateUrl("homepage"));
    }


    /**
     * @Route("/setSheetValue", name="setSheetValue")
     */
    public function setSheetValueAction(Request $request)
    {
        // replace this example code with whatever you need
        $root = $this->get('kernel')->getRootDir();

        $docId = $request->get('doc');
        $sheet = $request->get('sheet');
        $valuesToUpdate = $request->get('valuesToUpdate');

        $ecomSheet = $this->get('ecom.sheet_updater');
        
        foreach($valuesToUpdate as $cellIdentifier=>$newValue)
        {
            $ecomSheet->setSheetValue($docId, $cellIdentifier, $newValue);
        }
        
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
