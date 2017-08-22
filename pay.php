<html>
    <head>
        <link href="//fonts.googleapis.com/css?family=Lato:100" rel="stylesheet" type="text/css">
    </head>
<!--<body style="background: url('https://webpay3g.transbank.cl/webpayserver/imagenes/background.gif');">-->
    <body>
        <div style="width: 1150px; padding:0 15px; margin:0 auto;">
            <?php
            /**
            * @author     Allware Ltda. (http://www.allware.cl)
            * @copyright  2015 Transbank S.A. (http://www.tranbank.cl)
            * @date       Jan 2015
            * @license    GNU LGPL
            * @version    2.0.2
            */

            require_once( 'libwebpay/webpay.php' );
            require_once( 'certificates/cert-mall-normal.php' );
            include('registerWebPay.php');

            $conn = new Manager();

            /** Configuracion parametros de la clase Webpay */
            $sample_baseurl = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
            
            $configuration = new Configuration();
            $configuration->setEnvironment($certificate['environment']);
            $configuration->setCommerceCode($certificate['commerce_code']);
            $configuration->setPrivateKey($certificate['private_key']);
            $configuration->setPublicCert($certificate['public_cert']);
            $configuration->setWebpayCert($certificate['webpay_cert']);
            $configuration->setStoreCodes($certificate['store_codes']);

            /** Creacion Objeto Webpay */
            $webpay = new Webpay($configuration);

            $action = isset($_GET["action"]) ? $_GET["action"] : 'init';

            $post_array = false;

            switch ($action) {

                default:
                    $tx_step = "1: Inicio";

                    $stores = array();

                    //$buyOrder  = $_GET['orden']; // (Obligatorio) Es el código único de la orden de compra generada por el comercio mall.
                    $buyOrder  = rand();
                    //"amount" => $_GET['precio'],
                    $amount  = rand();
                    $sessionId = uniqid(); // (Opcional) Identificador de sesión, uso interno de comercio.
                    $urlReturn = $sample_baseurl."?action=getResult&orden=".$buyOrder."&amount=".$amount; // URL Retorno
                    $urlFinal  = $sample_baseurl."?action=end&amount=".$amount; // URL Final
 
                    $array = array(
                    //"storeCode" => $_GET['store_code'],
                    "storeCode" => "597020000543",
                    "amount" => $amount,
                    "buyOrder" => $buyOrder,
                    "sessionId" => uniqid(),
                    );
                    array_push($stores, $array);

                    $request = array(
                        "buyOrder"  => $buyOrder,
                        "sessionId" => $sessionId,
                        "urlReturn" => $urlReturn,
                        "urlFinal"  => $urlFinal,
                        "stores"  => $stores,
                    );
                    
                    /** Iniciamos Transaccion */
                    $result = $webpay->getMallNormalTransaction()->initTransaction($buyOrder, $sessionId, $urlReturn, $urlFinal, $stores);

                    /** Verificamos respuesta de inicio en webpay */
                    if (!empty($result->token) && isset($result->token)) {
                        $message = "Sesion iniciada con exito en Webpay";
                        $token = $result->token;
                        $next_page = $result->url;
                        $button = "Continuar";
                    } else {
                        $conn->updatePedido($buyOrder, 6 , 2, $amount);
                        $post_array = true;
                        $status = "rechazado";
                        $message = "error de certificado";
                        $token = "";
                        $next_page = $urlFinal;
                        $button = "Finalizar";
                    }
                    
                    break;

                case "getResult":
                    $tx_step = "2: Resultado";
                    
                    if (!isset($_POST["token_ws"])){
                        //Save Pedido DB
                        $conn->updatePedido($_GET["orden"], 6 , 2, $_GET["amount"]);
                        $post_array = true;
                        $status = "rechazado";
                        $message = "webpay no disponible";
                        $token = "";
                        $next_page = $sample_baseurl."?action=end&amount=".$amount;
                        $button = "Finalizar";
                    }else{
                        $token = filter_input(INPUT_POST, 'token_ws');
                    
                        $request = array(
                            "token" => filter_input(INPUT_POST, 'token_ws')
                        );

                        /** Rescatamos resultado y datos de la transaccion */
                        $result = $webpay->getMallNormalTransaction()->getTransactionResult($token);
               
                        /** Verificamos resultado del pago */
                        if ($result->detailOutput->responseCode === 0) {
                            
                            /** propiedad de HTML5 (web storage), que permite almacenar datos en nuestro navegador web */
                            echo '<script>window.localStorage.clear();</script>';
                            echo '<script>localStorage.setItem("commerceCode", '.$result->detailOutput->commerceCode.')</script>';
                            echo '<script>localStorage.setItem("authorizationCode", '.$result->detailOutput->authorizationCode.')</script>';
                            echo '<script>localStorage.setItem("amount", '.$result->detailOutput->amount.')</script>';
                            echo '<script>localStorage.setItem("buyOrder", '.$result->detailOutput->buyOrder.')</script>';
                            
                            //Save Log DB
                            $buyOrder = $result->buyOrder;
                            $sessionId = $result->sessionId;
                            $accountingDate = $result->accountingDate;
                            $transactionDate = $result->transactionDate;
                            $VCI = $result->VCI;
                            $cardNumber = $result->cardDetail->cardNumber;
                            $cardExpirationDate = $result->cardDetail->cardExpirationDate;
                            $authorizationCode = $result->detailOutput->authorizationCode;
                            $paymentTypeCode = $result->detailOutput->paymentTypeCode;
                            $responseCode = $result->detailOutput->responseCode;
                            $amount = $result->detailOutput->amount;
                            $sharesNumber = $result->detailOutput->sharesNumber;

                            $conn->insertStatusWebPay($buyOrder,$sessionId,$accountingDate,$transactionDate,$VCI,$cardNumber,$cardExpirationDate,$authorizationCode,$paymentTypeCode,$responseCode,$amount,$sharesNumber);

                            //Save Pedido DB
                            $conn->updatePedido($buyOrder, 2, 1, $amount);
                            $post_array = true;
                            $status = "aceptado";
                            $message = "Pago ACEPTADO por webpay";
                            $next_page = $result->urlRedirection;
                            $button = "Continuar";

                        } else {
                            if($result === null) {
                                $transactionDate = date('Y-m-d H:i:s');
                                
                                $conn->insertStatusWebPay($_GET['orden'],null,0,$transactionDate,null,null,null,null,null,'-3',0,0);
                                
                            } 
                            else {
                                $buyOrder = $result->buyOrder;
                                $sessionId = $result->sessionId;
                                $accountingDate = $result->accountingDate;
                                $transactionDate = $result->transactionDate;
                                $VCI = $result->VCI;
                                $cardNumber = $result->cardDetail->cardNumber;
                                $cardExpirationDate = $result->cardDetail->cardExpirationDate;
                                $authorizationCode = $result->detailOutput->authorizationCode;
                                $paymentTypeCode = $result->detailOutput->paymentTypeCode;
                                $responseCode = $result->detailOutput->responseCode;
                                $amount = $result->detailOutput->amount;
                                $sharesNumber = $result->detailOutput->sharesNumber;

                                $conn->insertStatusWebPay($buyOrder,$sessionId,$accountingDate,$transactionDate,$VCI,$cardNumber,$cardExpirationDate,$authorizationCode,$paymentTypeCode,$responseCode,$amount,$sharesNumber);
                            
                            }
                            //Save Pedido DB
                            if(empty($result->urlRedirection) && !isset($result->urlRedirection)) {
                                $next_page = $sample_baseurl."?action=end&amount=".$_GET['amount'];  
                                $button = "Continuar";
                            }
                            else {
                                $next_page = $result->urlRedirection;
                                $button = "Continuar";
                            }
                            
                            $conn->updatePedido($_GET['orden'], 6 , 2, $_GET['amount']);
                            $post_array = true;
                            $status = "rechazado";
                            $message = "Pago RECHAZADO por webpay en uno o mas comercios";
                            $button = "Finalizar";
                        }
                    }

                    
                    break;

                case "end":
                    
                    $tx_step = "3: Finalizar";
                    $request = '';
                    $result = $_POST;   

                    if(!empty($_POST['TBK_ORDEN_COMPRA']) && isset($_POST['TBK_ORDEN_COMPRA'])) {
                        //Save Pedido DB
                        $conn->updatePedido($result['TBK_ORDEN_COMPRA'], 6 , 2, $_GET['amount']);
                        $conn->insertStatusWebPay($result['TBK_ORDEN_COMPRA'],null,0,$transactionDate,null,null,null,null,null,'-9',$_GET['amount'],0);
                        $post_array = true;
                        $status = "rechazado";
                        $token = 0;
                        $message = "Transaccion Anulada";
                        $next_page = "http://anulado/confirmBuy";
                        $button = "Finalizar"; 
                    }
                    elseif($_POST['status'] == "rechazado") {
                        $post_array = true;
                        $status = "rechazado";
                        $token = 0;
                        $message = "Transaccion Rechazada";
                        $next_page = "http://rechazado/confirmBuy";
                        $button = "Finalizar";   
                    }
                    else {
                        $post_array = true;
                        $status = "aceptado";
                        $token = $_POST['token_ws'];
                        $message = "Transaccion Finalizada";
                        $next_page = "http://aprobado/confirmBuy";
                        $button = "Finalizar";
                    }

                    break;
                                                          
            }

            if (!isset($result)) {

                $result = "Ocurri&oacute; un error al procesar tu solicitud";
                echo "<div style = 'background-color:lightgrey;'><h3>result</h3>$result</div><br/><br/>";
                echo "<a href='.'>&laquo; volver a index</a>";
                die;
            }

            ?>

            <?php if ($post_array) { ?>

                    <form id="form" action="<?php echo $next_page; ?>" method="post">
                        <input type="hidden" name="token_ws" value="<?php echo ($token); ?>">
                        <input type="hidden" name="status" value="<?php echo ($status); ?>">
                    </form>

                    <script>
                        
                        var commerceCode = localStorage.getItem('commerceCode');
                        document.getElementById("commerceCode").value = commerceCode;
                        
                        var authorizationCode = localStorage.getItem('authorizationCode');
                        document.getElementById("authorizationCode").value = authorizationCode;
                        
                        var amount = localStorage.getItem('amount');
                        document.getElementById("amount").value = amount;
                        
                        var buyOrder = localStorage.getItem('buyOrder');
                        document.getElementById("buyOrder").value = buyOrder;
                        
                        localStorage.clear();
                        
                    </script>
                    
            <?php } elseif (strlen($next_page)) { ?>
                <form id="form" action="<?php echo $next_page; ?>" method="post">
                <input type="hidden" name="token_ws" value="<?php echo ($token); ?>">
            </form>
            <?php } ?>
            <script>document.getElementById('form').submit();</script>
        </div>
    </body>
</html>


