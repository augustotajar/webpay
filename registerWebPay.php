<?php
include('conexion.php');

class Manager {
    var $con;
 	function Manager() {
 		$this->con=new DBManager;
 	}
    
    function insertStatusWebPay($buyOrder,$sessionId,$accountingDate,$transactionDate,$VCI,$cardNumber,$cardExpirationDate,$authorizationCode,$paymentTypeCode,$responseCode,$amount,$sharesNumber) {
        
		$condate=date_format($transactionDate,"Y-m-d H:i:s");
        
        if($this->con->conectar()==true) {
            $query = "INSERT INTO `logWebpaySale` (
                         `buyOrder`
                        ,`sessionId`
                        ,`accountingDate`
                        ,`transactionDate`
                        ,`VCI`
                        ,`cardNumber`
                        ,`cardExpirationDate`
                        ,`authorizationCode`
                        ,`paymentTypeCode`
                        ,`responseCode`
                        ,`amount`
                        ,`sharesNumber`) 
                    VALUES (
                         '".$buyOrder."'
                        ,'".$sessionId."'
                        ,".$accountingDate."
                        ,'".$transactionDate."'
                        ,'".$VCI."'
                        ,'".$cardNumber."'
                        ,'".$cardExpirationDate."'
                        ,'".$authorizationCode."'
                        ,'".$paymentTypeCode."'
                        ,'".$responseCode."'
                        ,".$amount."
                        ,".$sharesNumber.");";
            
            $result = mysql_query($query);
			mysql_close($this->con->conectar());
			return $query; 
        }
    }
    
    function updatePedido($buyOrder,$estado,$estadoPago,$amount) {
        if($this->con->conectar()==true) {
            $query = "UPDATE pedidos SET estadoPago = ".$estadoPago.", estado = ".$estado.", total = ".$amount.", updated_pago = now() where op = '".$buyOrder."'";
            $result = mysql_query($query);
			mysql_close($this->con->conectar());
			return $result; 
        }
    }
}
?>