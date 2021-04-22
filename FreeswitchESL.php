<?php

class FreeswitchESL
{
    /**
     * Socket resource
     * TODO: undefined, on some php versions it returns a resource, recently returns a socket. 
     *
     * @var [type]
     */
    private $socket;

    /**
     * Structure type to be returned on the response whenever possible
     * 
     * @var string
     */
    private string $sorts = 'json';

    /**
     * Connects socket to the host
     *
     * @param string $host
     * @param int $port
     * @param string $password
     * @return bool
     */
    public function connect(string $host, int $port, string $password): bool
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($this->socket, $host, $port);
        $connect = false;
        $error = "";
        while ($socket_info = @socket_read($this->socket, 1024, PHP_NORMAL_READ)) {
            $eliminate_socket_info = $this->eliminate($socket_info);
            if ($eliminate_socket_info == "Content-Type:auth/request") {
                socket_write($this->socket, "auth " . $password . "\r\n\r\n");
            } elseif ($eliminate_socket_info == "") {
                continue;
            } elseif ($eliminate_socket_info == "Content-Type:command/reply") {
                continue;
            } elseif ($eliminate_socket_info == "Reply-Text:+OKaccepted") {
                $connect = true;
                break;
            } else {
                $error .= $eliminate_socket_info . "\r\n";
            }
        }
        if (!$connect) {
            echo $error;
        }
        return $connect;
    }

    /**
     * Submit commands to the socket host and waits for the response
     *
     * @param string $api
     * @param string $args
     * @return string
     */
    public function api($api, $args = ""): string
    {
        if ($this->socket) {
            socket_write($this->socket, "api " . $api . " " . $args . "\r\n\r\n");
        }
        $response = $this->recvEvent("common");
        return $response;
    }

    /**
     * Submit commands to the socket host and its executed in the background, returns true upon completion
     * TODO: Not implemented
     *
     * @param string $api
     * @param string $args
     * @return bool
     */
    public function bgapi($api, $args = ""): bool
    {
        if ($this->socket) {
            socket_write($this->socket, "bgapi " . $api . " " . $args . "\r\n\r\n");
        }
        return true;
    }

    /**
     * Listening to an event type
     *
     * @param string $type
     * @return string
     */
    public function recvEvent($type = "event"): string
    {
        $response = '';
        $length = 0;
        $x = 0;
        while ($socket_info = @socket_read($this->socket, 1024, PHP_NORMAL_READ)) {
            $x++;
            usleep(100);
            if ($length > 0) {
                $response .= $socket_info;
            }
            if ($length == 0 && strpos($socket_info, 'Content-Length:') !== false) {
                $lengtharray = explode("Content-Length:", $socket_info);
                if ($type == "event") {
                    $length = (int)$lengtharray[1] + 30;
                } else {
                    $length = (int)$lengtharray[1];
                }
            }

            if ($length > 0 && strlen($response) >= $length) {
                break;
            }

            if ($x > 10000) break;
        }

        if ($this->sorts == "json" && $type == "event") {
            $response = $this->typeClear($response);
            $responsedata = simplexml_load_string($response);
            $response = [];
            foreach ($responsedata->headers->children() as $key => $value) {
                $response[(string)$key] = (string)$value;
            }
            return json_encode($response);
        } else {
            $response = $this->eliminateLine($response);
        }
        return $response;
    }

    /**
     * Replacing break characters in order to get a better response for a more precise reading 
     *
     * @param string $parameter
     * @return string
     */
    private function eliminate($parameter): string
    {
        $array = array(" ", "　", "\t", "\n", "\r");
        return str_replace($array, '', $parameter);
    }

    /**
     * Replacing new line characters in order to get a better response for a more precise reading
     *
     * @param string $parameter
     * @return string
     */
    private function eliminateLine($parameter): string
    {
        return str_replace("\n\n", "\n", $parameter);
    }

    /**
     * Replacing content-type with emtpy strings for a more precise reading
     *
     * @param string $response
     * @return string
     */
    private function typeClear($response): string
    {
        $commenType = array("Content-Type: text/event-xml\n", "Content-Type: text/event-plain\n", "Content-Type: text/event-json\n");
        return str_replace($commenType, '', $response);
    }

    /**
     * Closes the socket connection
     *
     * @return void
     */
    public function disconnect(): void
    {
        socket_close($this->socket);
    }
}
