<?php
/************************************\
| Telegram-канал: https://t.me/z_tds |
| Cloudflare API v4                  |
| Добавление доменов и DNS записей   |
\************************************/

@set_time_limit(0);

ob_implicit_flush(true);
ob_end_flush();
// $email = 'fokin-m@list.ru';//email
// $api_key = 'd249b90ceef68f9087c5b5efaca3957935b70';//ключ API
// $domains = 'davidangelovoices.com';//список доменов через запятую
// $ip = '85.25.116.228';//ip
// $del = 0;//искать и удалять старые DNS записи (0/1)
// $auh = 1;//always use https (0/1)
// $ahr = 1;//automatic https rewrites (0/1)
// $pause = 10;//задержка перед поиском старых DNS записей (в cloudflare 60 секунд)

$email = trim($_POST['email']);
$api_key = trim($_POST['apikey']);
$domains = str_replace(PHP_EOL, ',', $_POST['domainsList']);
$ip = trim($_POST['ip']);
$del = (empty($_POST['delRecords'])) ? 0 : $_POST['delRecords'];
$auh = (empty($_POST['useHttps'])) ? 0 : $_POST['useHttps'];
$ahr = (empty($_POST['autoHttpsRewrites'])) ? 0 : $_POST['autoHttpsRewrites'];
$pause = (empty($_POST['pauseBeforeSearching'])) ? 10 : $_POST['pauseBeforeSearching'];

/*Ниже ничего не изменяйте*/
//таймаут для curl
$timeout_curl = 100;
$success = '<i class="fa fa-check"></i>';
$error = '<i class="fa fa-times"></i>';
//headers
$headers = array();
$headers[] = "X-Auth-Email: $email";
$headers[] = "X-Auth-Key: $api_key";
$headers[] = "Content-Type: application/json";
//добавляем домены
$domain = explode(',', $domains);
$x = 0;

while(trim($domain[$x]) != false){

	$d = trim($domain[$x]);
	//create zone
	$url = 'https://api.cloudflare.com/client/v4/zones';
	$data = '{"name":"'.$d.'", "jump_start":true}';
	$type = 'post';
	$response = array();

	$p = floor((($x + 1) / count($domain) *100)); //Progress
	$response['progress'] = $p;
	$response['domen'] = $d;
	curl();
	if($res->success){
		$response['createZone'] = '<tr><td>create zone '.$d.'</td><td class="status">'.$success.'</td></tr>';
		$id = $res->result->id;
		$ns1 = $res->result->name_servers[0];
		$ns2 = $res->result->name_servers[1];
		//поиск и удаление старых DNS записей
		if($del == 1){
			$url = "https://api.cloudflare.com/client/v4/zones/$id/dns_records";
			sleep ($pause);
			curl();
			$res1 = $res;
			$y = 0;
			while(!empty($res1->result[$y])){
				$id_zone = $res1->result[$y]->id;
				$name = $res1->result[$y]->name;
				$url = "https://api.cloudflare.com/client/v4/zones/$id/dns_records/$id_zone";
				$type = 'delete';
				curl();
				if($res->success){
					$response['deleteDnsRecord'] = '<tr><td>delete DNS record '.$name.'</td><td class="status">'.$success.'</td></tr>';
				}
				else{
					$response['deleteDnsRecord'] = '<tr><td>delete DNS record '.$name.'</td><td class="status">'.$error.'</td></tr>';
				}
				$y++;
			}
		}
		//добавление DNS записей
		$url = "https://api.cloudflare.com/client/v4/zones/$id/dns_records";
		$data = '{"type":"A","name":"'.$d.'","content":"'.$ip.'", "proxied":true}';
		$type = 'post';
		curl();
		if($res->success){
			$response['createDnsRecord'] = '<tr><td>create DNS record '.$d.'</td><td class="status">'.$success.'</td></tr>';
		}
		else{
			$response['createDnsRecord'] = '<tr><td>create DNS record '.$d.'</td><td class="status">'.$error.'</td></tr>';
		}
		$data = '{"type":"A","name":"www","content":"'.$ip.'", "proxied":true}';
		$type = 'post';
		curl();
		if($res->success){
			$response['createDnsRecordWww'] = '<tr><td>create DNS record www</td><td class="status">'.$success.'</td></tr>';
		}
		else{
			$response['createDnsRecordWww'] = '<tr><td>create DNS record www</td><td class="status">'.$error.'</td></tr>';
		}
		//always use https
		if($auh == 1){
			$url = "https://api.cloudflare.com/client/v4/zones/$id/settings/always_use_https";
			$data = '{"value":"on"}';
			$type = 'patch';
			curl();
			if($res->success){
				$response['alwaysUseHttps'] = '<tr><td>always use https</td><td class="status">'.$success.'</td></tr>';
			}
			else{
				$response['alwaysUseHttps'] = '<tr><td>always use https</td><td class="status">'.$error.'</td></tr>';
			}
		}
		//automatic https rewrites
		if($ahr == 1){
			$url = "https://api.cloudflare.com/client/v4/zones/$id/settings/automatic_https_rewrites";
			$data = '{"value":"on"}';
			$type = 'patch';
			curl();
			if($res->success){
				$response['automaticHttpsRewrites'] = '<tr><td>automatic https rewrites</td><td class="status">'.$success.'</td></tr>';
			}
			else{
				$response['automaticHttpsRewrites'] = '<tr><td>automatic https rewrites</td><td class="status">'.$error.'</td></tr>';
			}
		}
		$response['ns'] = '<tr><td>NS: '.$ns1.', '.$ns2.'</td></tr>';
	}
	else{
		$response['createZone'] = '<tr><td>create zone '.$d.'</td><td class="status">'.$error.'</td></tr>';
	}
	echo json_encode($response);


	$x++;
}


exit();
//
function curl(){
	global $url, $headers, $data, $res, $timeout_curl, $type;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout_curl);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	if($type == 'post'){
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	}
	if($type == 'delete'){
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
	}
	if($type == 'patch'){
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
	}
	$res = json_decode(curl_exec($ch));
	curl_close($ch);
	$type = '';
}
?>