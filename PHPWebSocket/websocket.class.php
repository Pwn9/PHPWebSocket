<?php  

// Usage: $master=new WebSocket("localhost",12345);

class WebSocket{
    var $master;
    var $sockets = array();
    var $users = array();
    var $debug = true;
  
    function __construct($address,$port){
        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush();

        $this->master=socket_create(AF_INET, SOCK_STREAM, SOL_TCP)     or die("socket_create() failed");
        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1)  or die("socket_option() failed");
        socket_bind($this->master, $address, $port)                    or die("socket_bind() failed");
        socket_listen($this->master,20)                                or die("socket_listen() failed");
        $this->sockets[] = $this->master;
        $this->say("Server Started : ".date('Y-m-d H:i:s'));
        $this->say("Listening on   : ".$address." port ".$port);
        $this->say("Master socket  : ".$this->master."\n");

        while(true){
            $changed = $this->sockets;
            socket_select($changed,$write=NULL,$except=NULL,NULL);
            foreach($changed as $socket){
                if($socket==$this->master){
                    $client=socket_accept($this->master);
                    if($client<0){ $this->log("socket_accept() failed"); continue; }
                    else{ $this->connect($client); }
                }
                else{
                    $bytes = @socket_recv($socket,$buffer,2048,0);
                    if($bytes==0){ $this->disconnect($socket); }
                    else{
                        $user = $this->getuserbysocket($socket);
                        if(!$user->handshake){ $this->dohandshake($user,$buffer); }
                        else{ $this->process($user,$this->unwrap($buffer)); }
                    }
                }
            }
        }
    }

    function process($user,$msg){
        /* Extend and modify this method to suit your needs */
        /* Basic usage is to echo incoming messages back to client */
        $this->send($user->socket,$msg);
    }

    function send($client,$msg){ 
        $this->say("> ".$msg);
        //$msg = $this->wrap($msg);
        socket_write($client,$msg,strlen($msg));
        $this->say("! ".strlen($msg));
    } 

    function connect($socket){
        $user = new User();
        $user->id = uniqid();
        $user->socket = $socket;
        array_push($this->users,$user);
        array_push($this->sockets,$socket);
        $this->log($socket." CONNECTED!");
        $this->log(date("d/n/Y ")."at ".date("H:i:s T"));
    }

    function disconnect($socket){
        $found=null;
        $n=count($this->users);
        for($i=0;$i<$n;$i++){
          if($this->users[$i]->socket==$socket){ $found=$i; break; }
        }
        if(!is_null($found)){ array_splice($this->users,$found,1); }
        $index=array_search($socket,$this->sockets);
        socket_close($socket);
        $this->log($socket." DISCONNECTED!");
        if($index>=0){ array_splice($this->sockets,$index,1); }
    }

    function dohandshake($user,$buffer){
        // UPDATED HANDSHAKE SEEMS TO WORK
        $this->log("\nRequesting handshake...");
        $this->log($buffer);
        list($resource,$host,$origin,$key) = $this->getheaders($buffer);
        $this->log("Handshaking...");

        $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                "Upgrade: WebSocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: ".base64_encode(sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true))."\r\n".
                "\r\n";
        socket_write($user->socket,$upgrade);
      
        $user->handshake=true;
        $this->log($upgrade);
        $this->log("Done handshaking...");
        return true;
    }
  
    function getheaders($req){
        $r=$h=$o=null;
        if(preg_match("/GET (.*) HTTP/"   ,$req,$match)){ $r=$match[1]; }
        if(preg_match("/Host: (.*)\r\n/"  ,$req,$match)){ $h=$match[1]; }
        if(preg_match("/Origin: (.*)\r\n/",$req,$match)){ $o=$match[1]; }
        if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/",$req,$match)){ $key1=$match[1]; }
        //if(preg_match("/\r\n(.*?)\$/",$req,$match)){ $data=$match[1]; }
        return array($r,$h,$o,$key1);
    }

    function getuserbysocket($socket){
        $found=null;
        foreach($this->users as $user){
          if($user->socket==$socket){ $found=$user; break; }
        }
        return $found;
    }

    function say($msg=""){
        echo $msg."\n"; 
    }
  
    function log($msg=""){ 
        if($this->debug) { 
            echo $msg."\n"; 
        } 
    }

    function wrap($msg=""){
    	
    	// THIS CODE DOES NOT SEEM TO BE WORKING
    	return chr(0).$msg.chr(255);
    	
       	/* 	THIS IS HOW THE BetterPHPWebSocket Does it
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($msg);
    
        if($length <= 125)
            $header = pack('CC', $b1, $length);
        elseif($length > 125 && $length < 65536)
            $header = pack('CCn', $b1, 126, $length);
        elseif($length >= 65536)
            $header = pack('CCNN', $b1, 127, $length);
        return $header.$msg;
        */
    

        /*  THIS CODE WORKS BUT ALSO THROWS MASK ERROR
        $length=strlen($msg);
        $header=chr(0x81).chr($length);
        $msg=$header.$msg;
        return $msg;  
        */
        
    }
  
    function unwrap($msg=""){ 
    	
    	// THIS CODE DOES NOT SEEM TO BE WORKING
    	return substr($msg,1,strlen($msg)-2);
       
    	/* 	THIS IS HOW THE BetterPHPWebSocket Does it
    	$length = ord($msg[1]) & 127;
        if($length == 126) {
            $masks = substr($msg, 4, 4);
            $data = substr($msg, 8);
        }
        elseif($length == 127) {
            $masks = substr($msg, 10, 4);
            $data = substr($msg, 14);
        }
        else {
            $masks = substr($msg, 2, 4);
            $data = substr($msg, 6);
        }
        $msg = "";
        for ($i = 0; $i < strlen($data); ++$i) {
            $msg .= $data[$i] ^ $masks[$i%4];
        }
        return $msg;   
        */  
       
        /* THIS CODE WORKS BUT ALSO THROWS MASK ERROR
        $firstMask=     bindec("10000000");
        $secondMask=    bindec("01000000"); //not doing anything with the rsvs since we arent negotiating extensions...
        $thirdMask=     bindec("00100000");
        $fourthMask=    bindec("00010000");
        $firstHalfMask= bindec("11110000");
        $secondHalfMask=bindec("00001111");
        $payload="";
        $firstHeader=ord(($msg[0]));
        $secondHeader=ord($msg[1]);
        $key=Array();
        $fin=(($firstHeader & $firstMask)?1:0);
        $rsv1=$rsv2=$rsv3=0;
        $opcode=$firstHeader & (~$firstHalfMask);//TODO: make the opcode do something. it extracts it but the program just assumes text;
        $masked=(($secondHeader & $firstMask) !=0);
        $length=$secondHeader & (~$firstMask);
        $index=2;
        if($length==126) {
            $length=ord($msg[$index])+ord($msg[$index+1]);
            $index+=2;
        }
        if($length==127) {
            $length=ord($msg[$index])+ord($msg[$index+1])+ord($msg[$index+2])+ord($msg[$index+3])+ord($msg[$index+4])+ord($msg[$index+5])+ord($msg[$index+6])+ord($msg[$index+7]);
            $index+=8;
        }
        if($masked) {
            for($x=0;$x<4;$x++) {
                $key[$x]=ord($msg[$index]);
                $index++;
            }
        }
        echo $length."\n";
        for($x=0;$x<$length;$x++) {
            $msgnum=ord($msg[$index]);
            $keynum=$key[$x % 4];
            $unmaskedKeynum=$msgnum ^ $keynum;
            $payload.=chr($unmaskedKeynum);
            $index++;
        }

        if($fin!=1) {
            return $payload.processMsg(substr($msg,$index));
        }
        return $payload;
        */
      
    }    

}

class User{
    var $id;
    var $socket;
    var $handshake;
}

?>
