<?php

/**
 * @name        PHP Proxy
 * @author      Jens Segers
 * @link        http://www.jenssegers.be
 * @license     MIT License Copyright (c) 2012 Jens Segers
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace phpproxy;

class Proxy
{
    
    // curl handle
    protected $ch;
    
    // configuration
    protected $config = array();

    function __construct($conf = array())
    {
        // load the config
        $config = array();
        require dirname(__FILE__)."/../../config.php";

        $config = array_merge($config, $conf);

        // check config
        if (!count($config)) {
            die("Please provide a valid configuration");
        }
        
        $this->config = $config;
        
        // initialize curl
        $this->ch = curl_init();
        @curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->config["timeout"]);
    }
    
    /*
     * Forward the current request to this url
     */
    function forward($url)
    {
        // build the correct url
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
            $url = "https://" . $this->config["server"] . ":" . $this->config["https_port"] . "/" . ltrim($url, "/");
        } else {
            $url = "http://" . $this->config["server"] . ":" . $this->config["http_port"] . "/" . ltrim($url, "/");
        }
        
        // set url
        curl_setopt($this->ch, CURLOPT_URL, $url);
        
        // forward request headers
        $headers = getallheaders();
        $this->set_request_headers($headers);
        
        // forward post
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $this->set_post($_POST);
        }
        
        // execute
        $data = curl_exec($this->ch);
        $info = curl_getinfo($this->ch);
        
        // extract response from headers
        $body = $info["size_download"] ? substr($data, $info["header_size"], $info["size_download"]) : "";
        
        // forward response headers
        $headers = substr($data, 0, $info["header_size"]);
        $this->set_response_headers($headers);
        
        // close connection
        curl_close($this->ch);
        
        // output html
        echo $body;
    }
    
    /*
     * Pass the request headers to cURL
     */
    function set_request_headers($request)
    {
        // headers to strip
        $strip = array("Content-Length", "Host");
        
        $headers = array();
        foreach ($request as $key => $value) {
            if ($key && !in_array($key, $strip)) {
                $headers[] = "$key: $value";
            }
        }
        
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    /*
     * Pass the cURL response headers to the user
     */
    function set_response_headers($response)
    {
        // headers to strip
        $strip = array("Transfer-Encoding");
        
        // split headers into an array
        $headers = explode("\n", $response);
        
        // process response headers
        foreach ($headers as &$header) {
            // skip empty headers
            if (!$header) {
                continue;
            }
            
            // get header key
            $pos = strpos($header, ":");
            $key = substr($header, 0, $pos);
            
            // modify redirects
            if (strtolower($key) == "location") {
                $base_url = $_SERVER["HTTP_HOST"];
                $base_url .= rtrim(str_replace(basename($_SERVER["SCRIPT_NAME"]), "", $_SERVER["SCRIPT_NAME"]), "/");
                
                // replace ports and forward url
                $header = str_replace(":" . $this->config["http_port"], "", $header);
                $header = str_replace(":" . $this->config["https_port"], "", $header);
                $header = str_replace($this->config["server"], $base_url, $header);
            }
            
            // set headers
            if (!in_array($key, $strip)) {
                header($header, FALSE);
            }
        }
    }
    
    /*
     * Set POST values including FILES support
     */
    function set_post($post)
    {
        // file upload support
        if (count($_FILES)) {
            foreach ($_FILES as $key => $file) {
                $parts = pathinfo($file["tmp_name"]);
                $name = $parts["dirname"] . "/" . $file["name"];
                rename($file["tmp_name"], $name);
                $post[$key] = "@" . $name;
            }
        } else {
            $post = http_build_query($post);
        }
        
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
    }

}