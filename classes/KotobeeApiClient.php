<?php
/**
 * An implementation to Kotobee REST API
 */

class KotobeeApiClient
{
    private $serial;
    private $domain;
    private $debugging = WP_DEBUG;

    function __construct($serial, $domain)
    {
        $this->serial = $serial;
        if (!$domain) 
            $domain = 'https://www.kotobee.com/';
        $this->domain = trailingslashit($domain);
    }

    private function remotePost($route, $args = array()) {
        $url = trailingslashit($this->domain) . 'api/v1/' . $route;

        $response = wp_remote_post($url, $args);
        if($this->debugging) {
            error_log("KotobeeAPI url: $url");
            error_log("KotobeeAPI args: ".print_r($args, true));
            if(is_wp_error($response))
                error_log("KotobeeAPI response:".print_r($response->get_error_message(), true));
            else
                error_log("KotobeeAPI response:".print_r($response['body'], true));
        }
        return $response;
    }
    public function serialCheck($serial = null, $domain = null) {
        $currentSerial = $this->serial;
        $currentDomain = $this->domain;
        if($serial) 
            $this->serial = $serial;
        if ($domain)
            $this->domain = $domain;

        $response = $this->getUserCloudBooks();
        $this->serial = $currentSerial;
        $this->domain = $currentDomain;
        
        return $response['Success'];
    }
    private function prepareUserObject($args) {
        $object = array();

        $object['serial'] = $this->serial;

        if(isset($args['email']))           $object['email'] =      $args['email'];
        if(isset($args['name']))            $object['name'] =       $args['name'];
        if(isset($args['organization']))    $object['organization'] = $args['organization'];
        if(isset($args['dept']))            $object['dept'] =       $args['dept'];
        if(isset($args['country']))         $object['country'] =    $args['country'];
        if(isset($args['info']))            $object['info'] =       $args['info'];
        if(isset($args['pwd']))             $object['pwd'] =        $args['pwd'];
        if(isset($args['uid']))             $object['uid'] =        $args['uid'];
        if(isset($args['libid']))           $object['libid'] =      $args['libid'];
        if(isset($args['catid']))           $object['catid'] =      $args['catid'];
        if(isset($args['bid']))             $object['bid'] =        $args['bid'];
        if(isset($args['cid']))             $object['cid'] =        $args['cid'];
        if(isset($args['rid']))             $object['rid'] =        $args['rid'];
        if(isset($args['active']))          $object['active'] =     $args['active'];
        if(isset($args['noemail']))         $object['noemail'] =    $args['noemail'];
        if(isset($args['activationemail'])) $object['activationemail'] = $args['activationemail'];
        if(isset($args['deleteall']))       $object['deleteall'] =  $args['deleteall'];
        if(isset($args['chapters'])) {
            $object['chapters'] =   $args['chapters'];
            $object['chaptersappend'] =   1;
        }        

        return $object;
    }
    private function prepareResponse($response, $bool = true) {
        $result = array(
            "Success" => false,
            "Message" => '',
            "Object" => array()
        );
        if(is_wp_error($response)){
            error_log("Error: Could not send API call to Kotobee ". $response->get_error_message());
            $result["Message"] = "connection_error";
            return $result;
        }
        $response = json_decode($response['body'], true);
        if(isset($response['error'])) {
            $result['Message'] = $response['error'];
            return $result;
        }
        $result['Success'] = true;
        if(!$bool)
            $result['Object'] = $response;

        return $result;

    }

    /**
     * @param $args
     * @return array
     */
    function addUser($args) {
        $args = $this->prepareUserObject($args);
        $result = $this->remotePost( 'user/add', array( 'body'=> $args ) );
        return $this->prepareResponse($result);
    }
    function deleteUser($args) {
        $args = $this->prepareUserObject($args);
        $result = $this->remotePost( 'user/delete', array( 'body'=> $args ) );
        return $this->prepareResponse($result);
    }
    function editUser($args) {
        $args = $this->prepareUserObject($args);
        $result = $this->remotePost( 'user/edit', array( 'body'=> $args ) );
        return $this->prepareResponse($result);
    }
    function deactivateUser($args) {
        $args['active'] = 0;

        $args = $this->prepareUserObject($args);
        $result = $this->remotePost( 'user/edit', array( 'body'=> $args ) );
        return $this->prepareResponse($result);
    }
    /**
     * @param string $libOrBook
     * @param string $hostedOrCloud
     * @return array|object
     */
    private function getUserContent($libOrBook = 'book', $hostedOrCloud = 'cloud') {
        $serial = $this->serial;
        $response = $this->remotePost( $libOrBook.'/all', array(
            'body'=> array(
                'serial'    =>$serial,
                'type'      =>$hostedOrCloud,
                'simple'    => 1
            ) ) );
        return $this->prepareResponse($response, false);
    }
    function getUserLibraries($detailed = true) {
        $libs = $this->getUserContent('library');
        if(!$detailed)
            return $libs;
        if(!$libs['Success'])
            return $libs;
        if(!count($libs['Object']))
            return $libs;

        $result = array("Success" => true, "Object"=> array());

        foreach($libs['Object'] as $library) {
            //Filter out libraries the user is not owner or admin on.
            if($library['auth'] != 'owner' && $library['auth'] != 'admin')
                continue;

            $response = $this->remotePost('library/get',
                array(
                    'body'=> array(
                        'serial'=>$this->serial,
                        'id'=>$library['id'],
//                        'simple' => 1
                    )
                )
            );
            $response = $this->prepareResponse($response, false);
            if(!$response['Success'])
                error_log($response['Message']);
            else{
                if(isset($response['Object']['file'])) {
                    unset($response['Object']['file']);
                    if($this->debugging)
                        kotobee_log($response['Object'], "Object unset: ");
                }
                $result['Object'][] = $response['Object'];
            }

        }
        return $result;
    }
    function getUserCloudBooks() {
        return $this->getUserContent('book');
    }
    function getUserLibrariesAndCloudBooks() {
        $libs = $this->getUserLibraries();
        $books = $this->getUserCloudBooks();

        //If everything is ok, return the objects
        if($libs['Success'] && $books['Success'])
            return array(
              'Success' => true,
              'Message' => '',
              'Object' => array(
                  'libraries' => $libs['Object'],
                  'books' => $books['Object']
              )
            );

        //If something is wrong, return the failed object
        if(!$libs['Success'])
            return $libs;
        return $books;
    }


}