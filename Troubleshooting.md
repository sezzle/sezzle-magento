# Invalid header line detected
This error occurs when your server sends a HTTP/2 request and Sezzle servers respond with HTTP/2 as the first header in the response. The Zend library in magento does not support header to be in the format `HTTP/2` (which is valid). It does a regex check for the header to be in the format `HTTP/<major>.<minor>`. Following is the solution:
    * In `\Zend\Http\Response`, in the `constructor`, after `$this->body = $body;` insert the following code

    ```php
    if ($version == '2') {
        $version = '2.0';
    }
    ```

    * In the same class, in `extractHeaders` function, replace regex `#^HTTP/\d+(?:\.\d+) [1-5]\d+#` with `#^HTTP/\d+(?:\.\d+)? [1-5]\d+#`.