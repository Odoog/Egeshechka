<?php

	use GuzzleHttp\Client;

	$MAX_LENGTH_BEFORE_SEPARATE = 26;

	function make_buttons($answerArray){
		global $MAX_LENGTH_BEFORE_SEPARATE;
		if(!$answerArray) return $answerArray;
		$length = 0;
		foreach ($answerArray as $key => &$value) {
			if($value["text"] == "NULL"){
				$value["text"] = "Свой ответ пишите на клавиатуре";
				return [[$answerArray[0]], [$answerArray[1]]];
			}
			print($value["text"] . "\n");
			$length += mb_strlen($value["text"], 'utf-8');
		}
		if($length < $MAX_LENGTH_BEFORE_SEPARATE){
			return [$answerArray];
		} else {
			foreach ($answerArray as $key => &$value) {
				$value = [$value];
			}
			return $answerArray;
		}
	}

	class TelegramBot{
		protected $token = "462339932:AAHprUeobJm24zMr59-jA1hrh1aQe8tqazI";

		protected $updateId;

		protected function query($method, $params = []){
			try {
				$url = "https://api.telegram.org/bot";
				$url .= $this->token;

				$url .= "/" . $method;

				if(!empty($params)){
					$url .= "?" . http_build_query($params);
				}

				$client = new Client([
					'base_uri' => $url,
					'proxy' => [
        				'http'  => 'tcp://5.135.164.72:3128', // Use this proxy with "http"
        				'https' => 'tcp://5.135.164.72:3128', // Use this proxy with "https",
    				]
				]);

				$result = $client->request('GET');

				return json_decode($result->getBody());
			} catch(Exception $e) {
				print('Выброшено исключение: '.  $e->getMessage(). "\n");
			}

		}

		public function getUpdates(){
			$response = $this->query('getUpdates', [
				'offset' => $this->updateId + 1
			]);

			if(!empty($response->result)){
				$this->updateId = $response->result[count($response->result) - 1]->update_id;
			}

			return $response->result;
		}

		public function sendMessage($chat_id, $text, $answers = NULL){
			$answers = make_buttons($answers);
			$postfields = [
				'chat_id' => "$chat_id",
				'text' => "$text"
			];
			if($answers){ 
				$keyboard = array(
					"keyboard" => $answers,
					"one_time_keyboard" => true, // можно заменить на FALSE,клавиатура скроется после нажатия кнопки автоматически при True
					"resize_keyboard" => true // можно заменить на FALSE, клавиатура будет использовать компактный размер автоматически при True
				);
				$postfields['reply_markup'] = json_encode($keyboard);
			};
			$output = $this->query("sendMessage", $postfields);
		}

		public function sendPhoto($chat_id, $photoAdress, $text = NULL, $answers = NULL){
			$answers = make_buttons($answers);
			$postfields = array(
				'photo' => $photoAdress,
				'chat_id' => $chat_id,
			);
			if($answers){
				$keyboard = array(
					"keyboard" => $answers,
					"one_time_keyboard" => false, // можно заменить на FALSE,клавиатура скроется после нажатия кнопки автоматически при True
					"resize_keyboard" => true // можно заменить на FALSE, клавиатура будет использовать компактный размер автоматически при True
				);
				$postfields['reply_markup'] = json_encode($keyboard);
			}
			if($text){
				$postfields['caption'] = $text;
			}
			$this->query("sendPhoto", $postfields);
		}

		public function sendMapPoint($chat_id, $latitude, $longitude, $answers = NULL){
			$answers = make_buttons($answers);
			$postfields = array(
				'latitude' => $latitude,
				'longitude' => $longitude,
				'chat_id' => $chat_id,
			);
			if($answers){
				$keyboard = array(
					"keyboard" => $answers,
					"one_time_keyboard" => true, // можно заменить на FALSE,клавиатура скроется после нажатия кнопки автоматически при True
					"resize_keyboard" => true // можно заменить на FALSE, клавиатура будет использовать компактный размер автоматически при True
				);
				$postfields['reply_markup'] = json_encode($keyboard);
			}
			$this->query("sendLocation", $postfields);
		}
	};

?>