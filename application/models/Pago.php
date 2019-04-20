<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 *
 */
class Pago extends CI_Model
{
    function __construct(){
        parent::__construct();
    }

    public function listarTodosCantidad (){
        /*$this->db->select('concepto');
        $this->db->from('pago');
        $this->db->group_by('concepto');*/
        $query = $this->db->query('SELECT concepto, COUNT(concepto) AS cantidad FROM pago GROUP BY pago.concepto');
        $data = $query->result_array();
        $array_out = array('labels'=>array(),'datasets'=>array());
        $dataset = array('label'=>'transacciones','data'=>array());
        foreach ($data as $concepto) {
            $array_out['labels'][] = $concepto['concepto'];
            $dataset['data'][] = $concepto['cantidad'];
        }
        $array_out['datasets'][] = $dataset;
        return $array_out;
    }

    public function listarTodosImporte (){
        $query = $this->db->query('SELECT concepto, SUM(importe) AS cantidad FROM pago GROUP BY pago.concepto');
        $data = $query->result_array();
        $array_out = array('labels'=>array(),'datasets'=>array());
        $dataset = array('label'=>'Importe','data'=>array());
        foreach ($data as $concepto) {
            $array_out['labels'][] = $concepto['concepto'];
            $dataset['data'][] = $concepto['cantidad'];
        }
        $array_out['datasets'][] = $dataset;
        return $array_out;
    }

    public function listarPorFechasCantidad($fecha_inicio, $fecha_fin, $conceptos){
        if (trim($conceptos) != ""){
            $condicional = "AND c.concepto::integer in (".str_replace ("|",",",$conceptos).")";
        }
        else{
            $condicional = "";
        }

        $query = $this->db->query("SELECT c.concepto AS concepto, r.importe AS cantidad, m.moneda AS tipo, r.fecha AS fecha
FROM public.recaudaciones r
         INNER JOIN public.concepto c ON (r.id_concepto = c.id_concepto)
         INNER JOIN public.clase_pagos p ON (p.id_clase_pagos = c.id_clase_pagos)
         INNER JOIN public.moneda m ON (r.moneda = m.id_moneda)
WHERE (
                  extract(epoch FROM r.fecha) >= ".$fecha_inicio."
              AND extract(epoch FROM r.fecha) <= ".$fecha_fin."
              AND p.id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S')
           ".$condicional."
        )");
        $data = $query->result_array();

        $finaldata = array();

        foreach ($data as $value)
        {

            if($value["tipo"]=="DOL")
            {
                $cambio = json_decode(file_get_contents("https://rocky-woodland-30485.herokuapp.com/cambio/".$value["fecha"]),true);

                $value["cantidad"]*=$cambio["compra"];
            }
            if(sizeof($finaldata)==0)
            {
                array_push($finaldata,array("concepto"=>$value["concepto"],"cantidad"=>$value["cantidad"]));
            }
            else{
                for($i=0; $i<sizeof($finaldata);$i++)
                {
                    if($finaldata["concepto"][i]==$value["concepto"])
                        $finaldata["cantidad"][i]+=$value["cantidad"];
                    else
                        array_push($finaldata,array("concepto"=>$value["concepto"],"cantidad"=>$value["cantidad"]));
                }
            }

        }

        $array_out = $this->formatoGrafico($finaldata,'Importes');
        return $array_out;
    }

    public function listarPorFechasImporte($fecha_inicio, $fecha_fin, $conceptos){
        if (trim($conceptos) != ""){
            $condicional = "AND c.concepto::integer in (".str_replace ("|",",",$conceptos).")";
        }
        else{
            $condicional = "";
        }

        $query = $this->db->query("SELECT c.concepto AS concepto, r.importe AS cantidad, m.moneda AS tipo, r.fecha AS fecha
FROM public.recaudaciones r
         INNER JOIN public.concepto c ON (r.id_concepto = c.id_concepto)
         INNER JOIN public.clase_pagos p ON (p.id_clase_pagos = c.id_clase_pagos)
         INNER JOIN public.moneda m ON (r.moneda = m.id_moneda)
WHERE (
                  extract(epoch FROM r.fecha) >= ".$fecha_inicio."
              AND extract(epoch FROM r.fecha) <= ".$fecha_fin."
              AND p.id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S')
           ".$condicional."
        ) order by concepto");
        $data = $query->result_array();

        $finaldata = array();


        foreach ($data as $value)
        {

            if($value["tipo"]=="DOL")
            {
                $cambio = json_decode(file_get_contents("https://rocky-woodland-30485.herokuapp.com/cambio/".$value["fecha"]),true);


                $value["cantidad"]*=$cambio["compra"];
            }

        }





        $array_out = $this->formatoGrafico($finaldata,'Monto');
        return $array_out;
    }

    public function listarAnioCantidad($year, $conceptos){
        if (trim($conceptos) != ""){
            $condicional = "AND c.concepto::integer in (".str_replace ("|",",",$conceptos).")";
        }
        else{
            $condicional = "";
        }

        $query = $this->db->query(
            "SELECT date_part('month',r.fecha) AS concepto,
                    COUNT(r.importe) AS cantidad
            FROM public.recaudaciones r
            INNER JOIN public.concepto c ON (r.id_concepto = c.id_concepto)
            INNER JOIN public.clase_pagos p ON (p.id_clase_pagos = c.id_clase_pagos)
            WHERE (
                date_part('year',fecha) = ".$year."
                AND p.id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S')
                 ".$condicional."
            )
            GROUP BY date_part('month',r.fecha)"
        );
        $data = $query->result_array();
        $array_out = $this->formatoGrafico($data,'Importes');
        $f_array_out = $this->formatoFecha($array_out);
        return $f_array_out;
    }
    public function test(){
        return "hola";
    }

    public function listarAnioImporte($year, $conceptos){
        if (trim($conceptos) != ""){
            $condicional = "AND c.concepto::integer in (".str_replace ("|",",",$conceptos).")";
        }
        else{
            $condicional = "";
        }

        $query = $this->db->query(
            "SELECT date_part('month',r.fecha) AS concepto,
                    SUM(importe) AS cantidad
            FROM public.recaudaciones r
            INNER JOIN public.concepto c ON (r.id_concepto = c.id_concepto)
            INNER JOIN public.clase_pagos p ON (p.id_clase_pagos = c.id_clase_pagos)
            WHERE (
                date_part('year',fecha) = ".$year."
                AND p.id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S')
                 ".$condicional."
            )
            GROUP BY date_part('month',r.fecha)"
        );
        $data = $query->result_array();
        $array_out = $this->formatoGrafico($data,'Monto');
        $f_array_out = $this->formatoFecha($array_out);
        return $f_array_out;
    }

    public function registrosPorFechas($fecha_inicio, $fecha_fin,$conceptos){
        if (trim($conceptos) != ""){
            $condicional = "AND c.concepto::integer in (".str_replace ("|",",",$conceptos).")";
        }
        else{
            $condicional = "";
        }
        $query = $this->db->query("SELECT c.concepto AS concepto, r.importe AS importe, trim(a.codigo) AS codigoAlumno, a.ape_nom AS nombreAlumno, to_char(r.fecha,'DD-MM-YYYY') AS fecha
            FROM public.recaudaciones r
                INNER JOIN public.concepto c
                    ON (r.id_concepto = c.id_concepto)
                INNER JOIN public.alumno a
                    ON (r.id_alum = a.id_alum)
                INNER JOIN public.clase_pagos p
                    ON (p.id_clase_pagos = c.id_clase_pagos)
            WHERE (
                extract(epoch FROM r.fecha) >= ".$fecha_inicio."
                AND extract(epoch FROM r.fecha) <= ".$fecha_fin."
                AND p.id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S')
                 ".$condicional."
            )
            ORDER BY to_char(r.fecha,'YYYY-MM-DD')");
        $data = $query->result_array();
        $array_out = $this->formatoTabla($data);
        return $array_out;
    }

    public function registrosPorAnio($yearStart, $yearEnd ,$conceptos){
        if (trim($conceptos) != ""){
            $condicional = "AND c.concepto::integer in (".str_replace ("|",",",$conceptos).")";
        }
        else{
            $condicional = "";
        }
        $query=$this->db->query("SELECT c.concepto AS concepto, r.importe AS importe, trim(a.codigo) AS codigoAlumno, a.ape_nom AS nombreAlumno, to_char(r.fecha,'DD-MM-YYYY') AS fecha
            FROM public.recaudaciones r
                INNER JOIN public.concepto c
                    ON (r.id_concepto = c.id_concepto)
                INNER JOIN public.alumno a
                    ON (r.id_alum = a.id_alum)
                INNER JOIN public.clase_pagos p
                    ON (p.id_clase_pagos = c.id_clase_pagos)
            WHERE (
                date_part('year',r.fecha) between ".$yearStart." and ".$yearEnd."
                AND p.id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S')
                 ".$condicional."
            )
            ORDER BY to_char(r.fecha,'YYYY-MM-DD')");
        $data = $query->result_array();
        $array_out = $this->formatoTabla($data);
        return $array_out;
    }
    public function registrosPorMes ($year,$startMonth,$endMonth, $conceptos){
        if (trim($conceptos) != ""){
            $condicional = "AND c.concepto::integer in (".str_replace ("|",",",$conceptos).")";
        }
        else{
            $condicional = "";
        }

        $query = $this->db->query(
        "SELECT c.concepto AS concepto,r.importe AS importe, trim(a.codigo) AS codigoAlumno, a.ape_nom AS nombreAlumno, to_char(r.fecha,'DD-MM-YYYY') AS fecha
        FROM public.recaudaciones r
        INNER JOIN public.concepto c ON (r.id_concepto = c.id_concepto)
        INNER JOIN public.clase_pagos p ON (p.id_clase_pagos = c.id_clase_pagos)
        INNER JOIN public.alumno a ON (r.id_alum = a.id_alum)
        WHERE (
            date_part('year',r.fecha) = ".$year."
            AND date_part('month',r.fecha) between ".$startMonth." and ".$endMonth."
            AND p.id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S')
             ".$condicional."
        )
        ORDER BY to_char(r.fecha,'YYYY-MM-DD')");

        $data = $query->result_array();
        $array_out = $this->formatoTabla($data);
        return $array_out;
    }

    //DE AÑO A OTRO A AÑO CANTIDAD/TOTAL
    public function listarCantidadPeriodoAnual($yearStart, $yearEnd, $conceptos){

        if (trim($conceptos) != ""){
            $condicional = "AND c.concepto::integer in (".str_replace ("|",",",$conceptos).")";
        }
        else{
            $condicional = "";
        }
        $query = $this->db->query(
        "SELECT date_part('year',r.fecha) AS concepto,COUNT(r.importe) AS cantidad
        FROM public.recaudaciones r
        INNER JOIN public.concepto c ON (r.id_concepto = c.id_concepto)
        INNER JOIN public.clase_pagos p ON (p.id_clase_pagos = c.id_clase_pagos)
        WHERE (
            date_part('year',r.fecha) between ".$yearStart." and ".$yearEnd."
            AND p.id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S')
             ".$condicional."
        )
        GROUP BY date_part('year',r.fecha);"
        );
        $data = $query->result_array();
        $array_out = $this->formatoGrafico($data,'Cantidad');
        return $array_out;

    }
    public function listarTotalPeriodoAnual($yearStart, $yearEnd, $conceptos){

        if (trim($conceptos) != ""){
            $condicional = "AND c.concepto::integer in (".str_replace ("|",",",$conceptos).")";
        }
        else{
            $condicional = "";
        }

        $query = $this->db->query(
        "SELECT date_part('year',r.fecha) AS concepto,SUM(r.importe) AS cantidad
        FROM public.recaudaciones r
        INNER JOIN public.concepto c ON (r.id_concepto = c.id_concepto)
        INNER JOIN public.clase_pagos p ON (p.id_clase_pagos = c.id_clase_pagos)
        WHERE (
            date_part('year',r.fecha) between ".$yearStart." and ".$yearEnd."
            AND p.id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S')
             ".$condicional."
        )
        GROUP BY date_part('year',r.fecha);"
        );
        $data = $query->result_array();
        $array_out = $this->formatoGrafico($data,'Monto');
        return $array_out;

    }

    //AÑO->mes inicial y fina
    public function listarCantidadPeriodoMensual($year,$startMonth,$endMonth, $conceptos){
        if (trim($conceptos) != ""){
            $condicional = "AND c.concepto::integer in (".str_replace ("|",",",$conceptos).")";
        }
        else{
            $condicional = "";
        }

        $query = $this->db->query(
        "SELECT date_part('month',r.fecha) AS concepto,
                COUNT(r.importe) AS cantidad
        FROM public.recaudaciones r
        INNER JOIN public.concepto c ON (r.id_concepto = c.id_concepto)
        INNER JOIN public.clase_pagos p ON (p.id_clase_pagos = c.id_clase_pagos)
        WHERE (
            date_part('year',r.fecha) = ".$year."
            AND date_part('month',r.fecha) between ".$startMonth." and ".$endMonth."
            AND p.id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S')
             ".$condicional."
        )
        GROUP BY date_part('month',r.fecha);"
        );
        $data = $query->result_array();
        $array_out = $this->formatoGrafico($data,'Cantidad');
        $f_array_out = $this->formatoFecha($array_out);
        return $f_array_out;

    }

    public function listarTotalPeriodoMensual($year,$startMonth,$endMonth, $conceptos){
        if (trim($conceptos) != ""){
            $condicional = "AND c.concepto::integer in (".str_replace ("|",",",$conceptos).")";
        }
        else{
            $condicional = "";
        }

        $query = $this->db->query(
        "SELECT date_part('month',r.fecha) AS concepto,
                SUM(r.importe) AS cantidad
        FROM public.recaudaciones r
        INNER JOIN public.concepto c ON (r.id_concepto = c.id_concepto)
        INNER JOIN public.clase_pagos p ON (p.id_clase_pagos = c.id_clase_pagos)
        WHERE (
            date_part('year',r.fecha) = ".$year."
            AND date_part('month',r.fecha) between ".$startMonth." and ".$endMonth."
            AND p.id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S')
             ".$condicional."
        )
        GROUP BY date_part('month',r.fecha);"
        );
        $data = $query->result_array();
        $array_out = $this->formatoGrafico($data,'Cantidad');
        $f_array_out = $this->formatoFecha($array_out);
        return $f_array_out;

    }

    public function listarConceptos(){
        $query = $this->db->query(
            "select concepto from public.concepto where id_clase_pagos in (SELECT distinct (id_clase_pagos) FROM configuracion where estado = 'S' )"
        );
        $data = $query->result_array();
        return $this->formatoConceptos($data);
    }

    private function formatoGrafico($data,$etiqueta){
        $array_out = array('labels'=>array(),'datasets'=>array());
        $dataset = array('label'=>$etiqueta,'data'=>array());
        if(count($data)>0){
            foreach ($data as $concepto) {

                $array_out['labels'][] = $concepto['concepto'];
                $dataset['data'][] = $concepto['cantidad'];
            }

        }
        $array_out['datasets'][] = $dataset;
        return $array_out;
    }

    private function formatoTabla($data){
        $array_out = array();
        if(count($data)>0){
            foreach ($data as $registro) {
                $array_out[] = $registro;
            }
        }
        return $array_out;
    }

    private function formatoFecha($data){
        if(count($data)>0){
            foreach($data["labels"] as $clave => $mes){
                if($mes == 1){
                    $data["labels"][$clave] = "Enero";
                } elseif($mes == 2){
                    $data["labels"][$clave] = "Febrero";
                }elseif($mes == 3){
                    $data["labels"][$clave] = "Marzo";
                }elseif($mes == 4){
                    $data["labels"][$clave] = "Abril";
                }elseif($mes == 5){
                    $data["labels"][$clave] = "Mayo";
                }elseif($mes == 6){
                    $data["labels"][$clave] = "Junio";
                }elseif($mes == 7){
                    $data["labels"][$clave] = "Julio";
                }elseif($mes == 8){
                    $data["labels"][$clave] = "Agosto";
                }elseif($mes == 9){
                    $data["labels"][$clave] = "Septiembre";
                }elseif($mes == 10){
                    $data["labels"][$clave] = "Octubre";
                }elseif($mes == 11){
                    $data["labels"][$clave] = "Noviembre";
                }elseif($mes == 12){
                    $data["labels"][$clave] = "Diciembre";
                }
            }
        }
        return $data;
    }

    private function formatoConceptos($data){
        $array_out = array("conceptos"=>array());
        if(count($data)>0){
            foreach ($data as $concepto) {
                $array_out['conceptos'][] = $concepto['concepto'];
            }
        }
        return $array_out;
    }

}



 ?>
