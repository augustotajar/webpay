<?php 
class DBManager
{
    public static function conectar()
	{
		$hostname = "localhost";
		$username = "root";
		$password = "1234";
		$database = "webpay";
		
		$conexion = mysql_connect($hostname, $username, $password) or die ("<h1> [:(] Error al conectar a la base de datos</h1>");
		mysql_query("SET NAMES 'utf8'");
        mysql_select_db($database) or die ("<h1>No puede seleccionar la base de datos</h1>" ); 
		
		return $conexion;
    }
}
?>