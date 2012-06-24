<?php

namespace INCES\ComedorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use INCES\ComedorBundle\Entity\Usuario;
use INCES\ComedorBundle\Entity\Menu;
use INCES\ComedorBundle\Entity\UsuarioMenu;
use INCES\ComedorBundle\Form\ContabilidadType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contabilidad controller.
 *
 */
class ContabilidadController extends Controller
{
    public function indexAction(){
        return new Response("hola");
    }

    public function isValid($value){
        $valid = false;
        $from = $value->get('from')->getData();
        $to   = $value->get('to')->getData();
        $rol  = $value->get('rol')->getData();
    }

    public function reporteIngresosAction(){

        $em = $this->getDoctrine()->getEntityManager();
        $request    = $this->getRequest();
        $filterForm = $this->createForm(new ContabilidadType());
        //$filterForm->bindRequest($request);

        if ($request->getMethod() == 'POST') {
            $filterForm->bindRequest($request);
            //print_r($filterForm->getData());
            //if($filterForm->isValid()){
            //if ($this->isValid($filterForm)) {
            if(true){

                /* TODO Hacer el calculo del dinero ganado */
                $montoTotal = 0;
                $cantidadTotal = 0;
                //print_r($filterForm->get('from')->getData()->format('d/m/Y'));
                $from  = $filterForm->get('from')->getData();
                $to    = $filterForm->get('to')->getData();
                $rol   = $filterForm->get('rol')->getData();
                $_roles = $em->getRepository('INCESComedorBundle:Rol')->findAll();
                //print_r($rol);
                //if($from != "") $from = $filterForm->get('from')->getData()->format('d/m/Y');
                //if($to   != "") $to   = $filterForm->get('to')->getData()->format('d/m/Y');

                $emConfig = $em->getConfiguration();
                $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
                $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
                $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');

                $dql = $em->createQueryBuilder();
                // First Case: Fechas vacio y rol vacio
                if($from == "" and $to == "" and $rol == "")
                    $dql->select('um', 'u', 'r')
                        ->from('INCESComedorBundle:UsuarioMenu', 'um')
                        ->join('um.usuario', 'u')
                        ->join('u.rol', 'r')
                        //->groupBy('r.id');
                        ->addOrderby('r.id', 'ASC');
                // Second Case: fechas no vacio y rol vacio
                elseif($from != "" and $to != "" and $rol == "" )
                    $dql->select('um', 'u', 'r')
                        ->from('INCESComedorBundle:UsuarioMenu', 'um')
                        ->join('um.usuario', 'u')
                        ->join('u.rol', 'r')
                        ->where('YEAR(um.dia)     <= ' .$to->format('Y'))
                        ->andWhere('MONTH(um.dia) <= ' .$to->format('m'))
                        ->andWhere('DAY(um.dia)   <= ' .$to->format('d'))
                        ->andWhere('YEAR(um.dia)  >= ' .$from->format('Y'))
                        ->andWhere('MONTH(um.dia) >= ' .$from->format('m'))
                        ->andWhere('DAY(um.dia)   >= ' .$from->format('d'))
                        //->andWhere('um.dia >= ' .$filterForm->get('from')->getData()->format('Y-m-d H:i:s'))
                        ->addOrderby('r.id', 'ASC');
                // Thrid Case: fechas vacio y rol no vacio
                elseif($from == "" and $to == "" and $rol != "" )
                    $dql->select('um', 'u', 'r')
                        ->from('INCESComedorBundle:UsuarioMenu', 'um')
                        ->join('um.usuario', 'u')
                        ->join('u.rol', 'r')
                        ->where('r.id = '. $rol->getId())
                        ->addOrderby('r.id', 'ASC');
                // Forth Case: fechas no vacio y rol no vacio
                else
                    $dql->select('um', 'u', 'r')
                        ->from('INCESComedorBundle:UsuarioMenu', 'um')
                        ->join('um.usuario', 'u')
                        ->join('u.rol', 'r')
                        ->where('r.id = '. $rol->getId())
                        ->andWhere('YEAR(um.dia)  <= ' .$to->format('Y'))
                        ->andWhere('MONTH(um.dia) <= ' .$to->format('m'))
                        ->andWhere('DAY(um.dia)   <= ' .$to->format('d'))
                        ->andWhere('YEAR(um.dia)  >= ' .$from->format('Y'))
                        ->andWhere('MONTH(um.dia) >= ' .$from->format('m'))
                        ->andWhere('DAY(um.dia)   >= ' .$from->format('d'))
                        ->addOrderby('r.id', 'ASC');

                $qry = $em->createQuery($dql);
                $pagination = $qry->getResult();
                /*
                $paginator  = $this->get('knp_paginator');
                $pagination = $paginator->paginate(
                    $qry,
                    $this->get('request')->query->get('page', 1),//page number
                    2//limit per page
                );
                 */

                // Get cantidadTotal
                $cantidadTotal = count($pagination);

                // Get monto total
                foreach($pagination as $value){
                    $montoTotal += intval($value->getUsuario()->getRol()->getMonto());
                }

                // Get quantity of users by rol
                $roles = array();
                $isOne = false;
                foreach($_roles as $rol){
                    foreach($pagination as $value){
                        if($rol == $value->getUsuario()->getRol()->getNombre()){
                            $temp = array((string)$rol => "1");
                            $roles = array_merge((array)$roles, (array)$temp);
                            $isOne = true;
                            break;
                        }
                    }
                    $temp = array((string)$rol => "0");
                    if(!$isOne) $roles = array_merge((array)$roles, (array)$temp); else $isOne = false;
                }

                return $this->render('INCESComedorBundle:Contabilidad:_reporte_ingresos.html.twig', array(
                     //'entities' => $entities
                     //'filter_form' => $filterForm->createView(),
                    'pagination'     => $pagination
                    ,'montoTotal'    => $montoTotal
                    ,'cantidadTotal' => $cantidadTotal
                    ,'from'          => $from
                    ,'to'            => $to
                    ,'roles'         => $roles
                    //,'one'           => $one
                ));
            }
        }

        return $this->render('INCESComedorBundle:Contabilidad:reporte_ingresos.html.twig', array(
             //'entities' => $entities
            'filter_form' => $filterForm->createView(),
        ));
    }
}