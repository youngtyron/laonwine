<?php 
	class Api {

	    public function getProduct($id) {
	    	if (!isset($id)) return [];
	    	$nomenclature = $this->getNomenclature();

	    	$products = isset($nomenclature['products']) ? $nomenclature['products']: null;
	    	$groups = isset($nomenclature['groups']) ? $nomenclature['groups']: [];

	    	if (!isset($products)) return [];
	    	$product = [];
			foreach ($products as $item) {
				if ($item['id']==$id) {
					$data = $item;
					break;
				}
			}	 
    	
			$product['name'] = isset($data['name']) ? $data['name'] : '';
			$product['price'] = isset($data['price']) ? $data['price'] : '';
			$product['vendor_code'] = isset($data['code']) ? $data['code'] : '';
			$product['facts'] = isset($data['additionalInfo']) ? $data['additionalInfo'] : '';
			$product['images'] = isset($data['images']) ? $data['images'] : [];

			$product['specifications'] = [];
			$specification_parts = explode("\n", $data['description']);
			foreach ($specification_parts as $specification_string) {
				$specification_point = explode(':', $specification_string);
				$title = $specification_point[0];
				$content = isset($specification_point[1]) ? $specification_point[1] : '';
				if (count($specification_point)>2) {
					foreach ($specification_point as $key => $part) {
						if ($key<2) continue;
						$content .= ":".$part;
					}
				}
				if (!$title || !$content) continue;
				$product['specifications'][$title] = $content;
			}
			$product['tags'] = [];
			foreach ($data['tags'] as $tags_string) {
				$tags_parts = explode("\n", $tags_string);		
				foreach ($tags_parts as $part) {
					if (strrpos($part, ":")) {
						$tag = $part;
					} else {
						if (isset($tag) && $part) $product['tags'][$tag][] = $part;
					}
				}
			}

			$catalogBranch = [];
			$parent = $data;			
			while($parent) {
				$parent = $this->findParent($parent['parentGroup'], $groups);
				if (isset($parent['name'])) $catalogBranch[] = ["name"=>$parent['name'], "url"=>""];
			}
			$product['catalog'] = array_reverse($catalogBranch);

			return $product;   	
	    }

	    protected function findParent($guid, $groups) {	    	
	    	foreach ($groups as $group) {
	    		if ($group['id']==$guid) return $group;
	    	}
	    	return false;
	    }

	    public function getNomenclature() {

	    	$this->auth();

	    	$report = $this->request("https://" . $this->client["biz_host"] . "/api/0/nomenclature/" . $this->organization , ["access_token" => $this->cardToken], "get", null);
			$nomenclature = json_decode($report, true);

			return $nomenclature;

	    }

	    public function auth() {

	    	require('settings.php');

	    	$this->client = isset($client_settings) ? $client_settings : [];

	    	$report = $this->request("https://" . $this->client["biz_host"] . "/api/0/auth/access_token", ["user_id" => $this->client["ext_client_id"], "user_secret" => $this->client["ext_token"]], "get", "data");

	    	$reportData = json_decode($report, true);
	    	if (is_array($reportData) && !empty($reportData)) return;

	    	$report = str_replace('"', "", $report);
	        if (!$report) return;
	            
	        $this->cardToken = $report;

	        $this->initOrganizations();
	        $this->organization = array_keys($this->organizations);
	        $this->organization = $this->organization[0];
	    }

	    protected function initOrganizations() {
	        
	        if (!$this->cardToken) return;
	        
	        $report = $this->request("https://" . $this->client["biz_host"] . "/api/0/organization/list", ["access_token" => $this->cardToken], "get");

	        if (isset($report["httpStatusCode"]) && $report["httpStatusCode"] >= 300) return;
	        
	        if (!is_array($report)) $report = [];

	        $orgs = [];
	        foreach ($report as $item) $orgs[$item["id"]] = $item;
	        
	        $this->organizations = $orgs;
	    }

	    protected function request($url = null, array $data = [], $type = "post", $format = "json") {
        
	       	$curl = curl_init();
	        
	        $requestData = http_build_query($data);
	        $suffix = ($type == "get" && $requestData) ? "?" . $requestData : "";
	        curl_setopt($curl, CURLOPT_URL, $url . $suffix);
	        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	        if ($type == "post") {
	            curl_setopt($curl, CURLOPT_POST, 1);
	            curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);
	        }
	        
	        curl_setopt($curl, CURLOPT_TIMEOUT, 120);
	        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 120);
	        
	        $response = curl_exec($curl);
	        $err = curl_error($curl);
	        
	        curl_close($curl);
	        
	        if ($err) {
	            return $err;
	        }
	        
	        if ($format == "json") {
	            $response = json_decode($response, true);
	            if (!$response) $response = [];
	        }

	        return $response;
	        
	    }

	}

 ?>
