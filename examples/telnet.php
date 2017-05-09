<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/9
 * Time: 下午10:55
 */

class Telnet
{
    private $sock;

    /**
     * Telnet constructor.
     * @param string $host
     * @param int $port
     */
    public function __construct($host, $port)
    {
        $this->sock = fsockopen($host, $port);

        socket_set_timeout($this->sock, 2, 0);
    }

    public function close()
    {
        if ($this->sock) {
            fclose($this->sock);
        }

        $this->sock = null;
    }

    public function write($buffer)
    {
        $buffer = str_replace(chr(255), chr(255) . chr(255), $buffer);

        fwrite($this->sock, $buffer);
    }

    public function command($command)
    {
        $this->write(trim($command) . "\r\n");
    }

    public function read($size = 1024)
    {
        return fread($this->sock, $size);
    }

    public function getc()
    {
        return fgetc($this->sock);
    }

    public function read_till($what)
    {
        $iac = chr(255);

        $dont = chr(254);
        $do = chr(253);

        $wont = chr(252);
        $will = chr(251);

        $theNull = chr(0);
        $buf = '';

        while (true) {
            $c = $this->getc();

            if ($c === false) {
                return $buf;
            }

            if ($c == $theNull) {
                continue;
            }

            if ($c == "1") {
                continue;
            }

            if ($c != $iac) {
                $buf .= $c;

                if ($what == (substr($buf, strlen($buf) - strlen($what)))) {
                    return $buf;
                } else {
                    continue;
                }
            }

            $c = $this->getc();

            if ($c == $iac) {
                $buf .= $c;
            } else if (($c == $do) || ($c == $dont)) {
                $opt = $this->getc();
                // echo "we wont ".ord($opt)."\n";
                fwrite($this->sock, $iac . $wont . $opt);
            } elseif (($c == $will) || ($c == $wont)) {
                $opt = $this->getc();
                // echo "we dont ".ord($opt)."\n";
                fwrite($this->sock, $iac . $dont . $opt);
            } else {
                // echo "where are we? c=".ord($c)."\n";
            }
        }
    }
}


$telnet = new Telnet("127.0.0.1",4730);

$telnet->command("status");

var_dump($telnet->read());

$telnet->command("workers");

var_dump($telnet->read());

//echo $telnet->read_till("status\r\n");
//
//echo $telnet->read_till("login: ");
//$telnet->write("kongxx");
//
//echo $telnet->read_till("password: ");
//$telnet->write("KONGXX");
//
//echo $telnet->read_till(":> ");
//$telnet->write("ls");
//
//echo $telnet->read_till(":> ");

$telnet->close();
