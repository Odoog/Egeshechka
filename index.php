<?php

	require __DIR__ . '/vendor/autoload.php';

	include('botFuck.php');

	$texts = Texts::getTexts();

	$todayQuestions = Questions::getQuestionsFromTodayQuestions();
	$archiveQuestions = Questions::getQuestionsFromArchive();
	$archiveQuestionsForRand = Questions::getQuestionsFromArchiveForRand();

	$newQuestionObject = [];
	$telegramApi = new TelegramBot();

	$buttons = [
		"start" => ["Архив вопросов", "Вопросы на сегодня", "Информация обо мне"],
		"archive_start" => ["Вопросы по темам", "Cлучайные вопросы", "Назад"],
		"information" => ["Изменить ник", "Продолжить"],
		"end" => ["Архив вопросов", "Оставить отзыв", "Информация обо мне"],
		"admin_start" => ["Вопросы", "Рейтинг", "Перезапустить день", "Рассылка"],
		"admin_rate" => ["Показать рейтинг", "Обнулить рейтинг", "Назад"],
		"admin_questions" => ["Группы", "Добавить вопрос", "Показать ежедневные вопросы", "Показать вопросы из архива", "Удалить последний вопрос", "Назад"],
		"admin_groups" => ["Добавить группу", "Добавить вопрос в группу", "Удалить группу", "Удалить вопрос из группы", "Назад"],
		"archive_questions_by_theme_end" => ["Назад"]
	];

	$marks = [
		"5" => "Отлично! Ты справился со всеми заданиями!",
		"4" => "Хорошо! В следующий раз ты сможешь решить все!",
		"3" => "У тебя отлично получается, потренируйся ещё!",
		"2" => "The roots of education are bitter, but the fruit is sweet",
		"1" => "Так, соберись, тряпка! Я верю в тебя!",
		"0" => "Мда. Знания у тебя не очень. Мне кажется тебе стоит лучше читать тему",
	];

	$goodAnswers = [
		"Да, точно, ты прав!",
		"Yep",
		"Да. Интересно, ты правда такой умный, или гуглишь?",
		"Пра Виль Но",
		"Ответ верный. Да брось, это было нереально.",
		"Ты прав конечно и даже бал зачтем.",
		"+ 1 бал за правильный ответ.",
		"Ну да так то.",
		"Правильный ответ.",
		"Ага."
	];

	$badAnswers = [
		"Неа.",
		"Нит.",
		"Nope.",
		"Ты не прав.",
		"Ошибочка.",
		"Ответы не сошлись.",
		"Правильный ответ другой.",
		"Тут ошибка, замечаешь?",
		"Нет Нет Нет.",
		"Сегодня не твой день."
	];

	class Texts{
		public static function getTexts(){
			$answer = [];
			$textsArray = mysqlQuest("SELECT * FROM `texts`", "Group");
			while($newText = mysqli_fetch_assoc($textsArray)){
				$answer[$newText["name"]] = $newText["text"];
			}
			return $answer;
		}
	}

	function makeAnswerArray($answerArray){
		global $separateButtonsFlag;
		$updateAnswerArray = [];
		foreach ($answerArray as $ind => $value) {
			$updateAnswerArray[] = ["text" => $value];
		}
		return $updateAnswerArray;
	}

	function mysqlQuest($quest, $type = "Single"){
		$connection = mysqli_connect('127.0.0.1', "root", '', "bot reload");
		$connection->set_charset('utf8mb4');
		$answer = mysqli_query($connection, $quest);
		if($answer){
			if($type == "Single") $answer = mysqli_fetch_assoc($answer);
			return $answer;
		} else {
			return false;
		} 
	}

	class Action{
		public static function text($message, $buttons = NULL, $targetChatId = NULL){
			global $chatId, $telegramApi;
			if($targetChatId){
				if($buttons){
					$telegramApi->sendMessage($targetChatId, $message, makeAnswerArray($buttons));
				} else {
					$telegramApi->sendMessage($targetChatId, $message);
				}
			} else {
				if($buttons){
					$telegramApi->sendMessage($chatId, $message, makeAnswerArray($buttons));
				} else {
					$telegramApi->sendMessage($chatId, $message);
				}
			}
		}
		public static function point($x, $y, $buttons = NULL){
			global $chatId, $telegramApi;
			if($buttons){
				$telegramApi->sendMapPoint($chatId, $x, $y, makeAnswerArray($buttons));
			} else {
				$telegramApi->sendMapPoint($chatId, $x, $y);
			}
		}
		public static function pic($picId, $message = NULL, $buttons = NULL){
			global $chatId, $telegramApi;
			if($buttons){
				$sendMessageObject = $telegramApi->sendPhoto($chatId, $picId, $message, makeAnswerArray($buttons));
			} else {
				$sendMessageObject = $telegramApi->sendPhoto($chatId, $picId, $message);
			}
			return $sendMessageObject->result->message_id;
		}
	}

	class Groups{

		public static $currentGroupToAddQuestion;

		public static $currentGroupToDeleteQuestion;

		public static function getAllGroupsNames(){
			$answer = "Группы: \n \n";

			$names = mysqlQuest("SELECT * FROM `questionGroups`", "Group");
			while($name = mysqli_fetch_assoc($names)){
				$answer .= $name['groupName'] . " " . $name['questions'] . "\n";
			}
			return $answer;
		}

		public static function addGroup($groupName){
			mysqlQuest("INSERT INTO `questionGroups`(`groupName`, `questions`) VALUES ('$groupName', '[]')");
		}

		public static function getAllGroupsButtons(){
			$answer = [];

			$names = mysqlQuest("SELECT `groupName` FROM `questionGroups`", "Group");
			while($name = mysqli_fetch_assoc($names)){
				$answer[] = $name['groupName'];
			}
			return $answer;
		}

		public static function deleteGroup($groupName){
			mysqlQuest("DELETE FROM `questionGroups` WHERE `groupName` = '$groupName'");
		}

		public static function pushQuestionInGroup($question){
			$groupName = Groups::$currentGroupToAddQuestion;
			$lastQuestionsArray = mysqlQuest("SELECT `questions` FROM `questionGroups` WHERE `groupName` = '$groupName'");
			$lastQuestionsArray = json_decode($lastQuestionsArray['questions']);
			if(mb_stripos($question, ",")){
				$array = mb_split(",", $question);
				foreach ($array as $key => &$value) {
					$value = $value + 0; // Из строки в число
				}
				$lastQuestionsArray = array_merge($lastQuestionsArray, $array);
			} else {
				$lastQuestionsArray[] = $question + 0;
			}
			$newQuestionsArray = json_encode($lastQuestionsArray, JSON_UNESCAPED_UNICODE);
			mysqlQuest("UPDATE `questionGroups` SET `questions` = '$newQuestionsArray' WHERE `groupName` = '$groupName'");
		}

		public static function deleteQuestionInGroup($question){
			$groupName = Groups::$currentGroupToDeleteQuestion;
			$lastQuestionsArray = mysqlQuest("SELECT `questions` FROM `questionGroups` WHERE `groupName` = '$groupName'");
			$lastQuestionsArray = json_decode($lastQuestionsArray['questions']);
			$key = array_search($question + 0, $lastQuestionsArray);
			if (false !== $key) array_splice($lastQuestionsArray, $key, 1);
			$newQuestionsArray = json_encode($lastQuestionsArray, JSON_UNESCAPED_UNICODE);
			mysqlQuest("UPDATE `questionGroups` SET `questions` = '$newQuestionsArray' WHERE `groupName` = '$groupName'");
		}

		public static function getQuestionArrayFromGroup($groupName){
			$lastQuestionsArray = mysqlQuest("SELECT `questions` FROM `questionGroups` WHERE `groupName` = '$groupName'");
			$lastQuestionsArray = json_decode($lastQuestionsArray['questions']);
			return $lastQuestionsArray;
		}

	}

	class Users{
		public static function updatePassTodayQuestions(){
			global $user;
			mysqlQuest("UPDATE `users` SET `passTodayQuestions`= 0");
			$user["passTodayQuestions"] = 0;
		}

		public static function setTodayRateToZero(){
			mysqlQuest("UPDATE `users` SET `todayRate`= 0");
		}

		public static function mailing(){
			global $buttons, $texts;
			$usersList = mysqlQuest("SELECT * FROM `users`", "Group");
			mysqlQuest("UPDATE `users` SET `stage` = 'start'");
			while($user = mysqli_fetch_assoc($usersList)){
				Action::text("Привет, " . $user['nick'] . ". " . $texts['start'] , $buttons['start'], $user['chatId']);
			}
		}

	}

	class User{
		public static $currentStage;

		public static function newUser(){
			global $userId;
			mysqlQuest("INSERT INTO `users`(`id`, `stage`) VALUES ('$userId', 'старт')");
		}
		
		public static function updateNick($newNick){
			global $userId, $user;
			mysqlQuest("UPDATE `users` SET `nick` = '$newNick' WHERE `id` = $userId");
			$user["nick"] = $newNick;
		}

		public static function updatePassTodayQuestions(){
			global $userId, $user;
			mysqlQuest("UPDATE `users` SET `passTodayQuestions`= 1 WHERE `id` = $userId");
			$user["passTodayQuestions"] = 1;
		}

		public static function updateStage($newStage){
			global $userId, $user;
			mysqlQuest("UPDATE `users` SET `stage`= '$newStage' WHERE `id` = $userId"); 
			$user["stage"] = $newStage;
		}

		public static function getUser($userId){
			$answer = mysqlQuest("SELECT * FROM `users` WHERE `id` = $userId");
			if($answer) $answer['currentThemeArray'] = json_decode($answer['currentThemeArray']);
			return $answer;
		}

		public static function getInformation(){
			global $user, $userId;
			$answer = "Информация о вашем профиле \n";
			$answer .= "Ваш ник: " . $user["nick"] . "\n";
			$answer .= "Ваш рейтинг за все время: " . $user["rate"] . "\n";
			$answer .= "Ваш cегодняшний рейтинг: " . $user["todayRate"] . "\n";
			$answer .= "Ваши db параметры: [" . rand(0, 10) . "," . rand(0, 2200) . "," . rand(0, 2) . "," . rand(0, 100000) . "," . rand(0, 5) . "," . rand(0, 3000120000) . "]";
			return $answer;
		}

		public static function updatelastQuestionWeAsked($newIndex){
			global $user, $userId;
			mysqlQuest("UPDATE `users` SET `lastQuestionWeAsked` = $newIndex WHERE `id` = $userId");
			$user['lastQuestionWeAsked'] = $newIndex;
		}

		public static function todayRateIncrease(){
			global $user, $userId;
			$newTodayRate = $user['todayRate'] + 1;
			$newRate = $user['rate'] + 1;
			$user['todayRate'] = $newTodayRate;
			$user['rate'] = $newRate;
			mysqlQuest("UPDATE `users` SET `todayRate` = $newTodayRate WHERE `id` = $userId");
			mysqlQuest("UPDATE `users` SET `rate` = $newRate WHERE `id` = $userId");
		}

		public static function getTodayStatistics(){
			global $user, $todayQuestions, $marks;
			$allQuestionsWereToday = count($todayQuestions);
			$userCouldAnswer = $user['todayRate'];
			$ratio = $userCouldAnswer / $allQuestionsWereToday * 5;
			$mark = intval($ratio);
			$answer = "За сегодня вы решили " . $userCouldAnswer . " из " . $allQuestionsWereToday . "\n";
			$answer .= $marks[$mark];
			return $answer;
		}

		public static function updateCurrentThemeArray($newCurrentThemeArray){
			global $user, $userId;
			$user['currentThemeArray'] = $newCurrentThemeArray;
			$newCurrentThemeArray = json_encode($newCurrentThemeArray, JSON_UNESCAPED_UNICODE);
			mysqlQuest("UPDATE `users` SET `currentThemeArray` = '$newCurrentThemeArray' WHERE `id` = $userId");
		}

		public static function updateChatId($newChatId){
			global $user, $userId;
			$user['chatId'] = $newChatId;
			mysqlQuest("UPDATE `users` SET `chatId` = '$newChatId' WHERE `id` = $userId");	
		}
	}

	class Reviews{
		public static function addReview($newReview){
			mysqlQuest("INSERT INTO `reviews` (`text`) VALUES ('$newReview')");
		}
	}

	class Questions{

		public static function addNewQuestion($questionObject){
			global $todayQuestions;
			$text = $questionObject['text'];
			$answers = json_encode($questionObject['answers'], JSON_UNESCAPED_UNICODE);
			$rightAnswer = $questionObject['rightAnswer'];
			mysqlQuest("INSERT INTO `questionsToday` (`text`, `answers`, `rightAnswer`) VALUES ('$text', '$answers', '$rightAnswer')");
			$todayQuestions = Questions::getQuestionsFromTodayQuestions();
		}

		public static function getQuestionsFromTodayQuestions(){
			$answer = [];
			$questionsFromBase = mysqlQuest("SELECT * FROM `questionsToday` ORDER BY `ind`", "group");
			while ($questionFromBase = mysqli_fetch_assoc($questionsFromBase)) {
				$questionObject = [];
				$questionObject['text'] = $questionFromBase['text'];
				$questionObject['answers'] = json_decode($questionFromBase['answers']);
				$questionObject['rightAnswer'] = $questionFromBase['rightAnswer'];
				$questionObject['ind'] = $questionFromBase['ind'];	
				$answer[] = $questionObject;
			}
			return $answer;
		}

		public static function getQuestionsFromArchiveText(){
			global $archiveQuestions;
			$text = "";
			foreach ($archiveQuestions as $key => $question) {
				$text .= $key . " | ";
				$questionText = $question['text'];
				$text .= mb_substr($questionText, 0, 20, "utf-8") . "... \n";
			}
			return $text;
		}

		public static function getQuestionsFromArchive(){
			$answer = [];
			$questionsFromBase = mysqlQuest("SELECT * FROM `archive` ORDER BY `ind`", "group");
			while ($questionFromBase = mysqli_fetch_assoc($questionsFromBase)) {
				$questionObject = [];
				$questionObject['text'] = $questionFromBase['text'];
				$questionObject['answers'] = json_decode($questionFromBase['answers']);
				$questionObject['rightAnswer'] = $questionFromBase['rightAnswer'];
				$answer[$questionFromBase['ind']] = $questionObject;
			}
			return $answer;	
		}

		public static function getQuestionsFromArchiveForRand(){
			$answer = [];
			$questionsFromBase = mysqlQuest("SELECT * FROM `archive` ORDER BY `ind`", "group");
			while ($questionFromBase = mysqli_fetch_assoc($questionsFromBase)) {
				$questionObject = [];
				$questionObject['text'] = $questionFromBase['text'];
				$questionObject['answers'] = json_decode($questionFromBase['answers']);
				$questionObject['rightAnswer'] = $questionFromBase['rightAnswer'];
				$questionObject['ind'] = $questionFromBase['ind'];
				$answer[] = $questionObject;
			}
			return $answer;	
		}

		public static function makeAnswerArray($string){
			$answers = explode(",", $string);
			return $answers;
		}

		public static function deleteLastQuestion(){
			global $todayQuestions;
			$deleteObject = array_pop($todayQuestions);
			$deleteInd = $deleteObject['ind'];
			mysqlQuest("DELETE FROM `questionsToday` WHERE `ind` = $deleteInd");
		}

		public static function getOpinionAboutTheTodayQuestion($userAnswer){
			global $user, $todayQuestions, $goodAnswers, $badAnswers;
			$questionInd = $user['lastQuestionWeAsked'];
			$rightAnswer = $todayQuestions[$questionInd]['rightAnswer'];
			if(mb_strtolower($rightAnswer) == mb_strtolower($userAnswer)){
				$answer = $goodAnswers[rand(0, count($goodAnswers) - 1)] . "\n \n";
				User::todayRateIncrease();
			} else {
				$answer = $badAnswers[rand(0, count($badAnswers) - 1)] . " Правильный ответ был " . $rightAnswer . "\n \n";
			}
			return $answer;
		}

		public static function getOpinionAboutTheArchiveQuestion($userAnswer){
			global $user, $archiveQuestions, $goodAnswers, $badAnswers;
			$questionInd = $user['currentThemeArray'][$user['lastQuestionWeAsked']];
			$rightAnswer = $archiveQuestions[$questionInd]['rightAnswer'];
			if(mb_strtolower($rightAnswer) == mb_strtolower($userAnswer)){
				$answer = $goodAnswers[rand(0, count($goodAnswers) - 1)] . "\n \n";
			} else {
				$answer = $badAnswers[rand(0, count($badAnswers) - 1)] . " Правильный ответ был " . $rightAnswer . "\n \n";
			}
			return $answer;
		}

		public static function getOpinionAboutTheArchiveRandQuestion($userAnswer){
			global $user, $archiveQuestionsForRand, $goodAnswers, $badAnswers;
			$questionInd = $user['lastQuestionWeAsked'];
			$rightAnswer = $archiveQuestionsForRand[$questionInd]['rightAnswer'];
			if(mb_strtolower($rightAnswer) == mb_strtolower($userAnswer)){
				$answer = $goodAnswers[rand(0, count($goodAnswers) - 1)] . "\n \n";
			} else {
				$answer = $badAnswers[rand(0, count($badAnswers) - 1)] . " Правильный ответ был " . $rightAnswer . "\n \n";
			}
			return $answer;
		}

		public static function tranferQuestionsInTheArchive(){
			global $archiveQuestions, $archiveQuestionsForRand;
			mysqlQuest("INSERT INTO `archive` (`text`, `answers`, `rightAnswer`) 
				SELECT `text`, `answers`, `rightAnswer` FROM `questionsToday`");
			mysqlQuest("DELETE FROM `questionsToday`");
			$archiveQuestions = Questions::getQuestionsFromArchive();
			$archiveQuestionsForRand = Questions::getQuestionsFromArchiveForRand();
		}

		public static function showAllQuestions(){
			global $todayQuestions;
			$answer = "Все ежедневные вопросы в приложении: \n \n";
			foreach ($todayQuestions as $key => $value) {
				$answer .= "Номер: " . $key . "\n";
				$answer .= "Текст: " . $value['text'] . "\n";
				$answer .= "Варианты: ";
				foreach ($value['answers'] as $answersKey => $answersValue) {
					$answer .= $answersValue . ' ';
				}
				$answer .= "\n";
				$answer .= "Правильный ответ: " . $value['rightAnswer'];
				$answer .= "\n \n";
			}
			return $answer;
		}
	}

	class Rate{
		public static function showRate(){
			$rateBase = mysqlQuest("SELECT `nick`, `rate` FROM `users` ORDER BY `rate` DESC", "Group");
			$answer = "Рейтинг пользователей приложения: \n \n";
			while ($thisUser = mysqli_fetch_assoc($rateBase)) {
				$answer .= $thisUser['nick'] . " : " . $thisUser['rate'] . "\n";
			}
			return $answer;
		}

		public static function resetRate(){
			mysqlQuest("UPDATE `users` SET `rate` = 0 WHERE 1");
		}
	}

	while(true){
		
		$updates = $telegramApi->getUpdates();
		foreach($updates as $update){

			//
			// $dest = imagecreatefromjpeg('1.jpg');
			// $src = imagecreatefromjpeg('2.jpg');
			// imagecopymerge($dest, $src, 10, 9, 0, 0, 181, 180, 100);
			// imagejpeg($dest, '3.jpg');
			// continue;
			//


			$chatId = $update->message->chat->id;
			$userId = $update->message->from->id;
			$messageText = $update->message->text;


			$user = User::getUser($userId);

			if(!$user){
				User::newUser();
				Action::text($texts['name_undefined'] , $buttons['name_undefined']);
				User::updateStage('name_undefined');
				continue;
			}

			//Проверка после обновления, добавление юзерам chatId в бд

			if($user['chatId'] == "null"){
				User::updateChatId($chatId);
			}

			//

			if($messageText == 'Админ'){
				Action::text($texts['admin_start'], $buttons['admin_start']);
				User::updateStage('admin_start');
				continue;
			}

			if($messageText == 'Юзер'){
				User::updateStage('start');
				Action::text("Привет, " . $user['nick'] . ". " . $texts['start'] , $buttons['start']);
				continue;
			}

			switch ($user['stage']){

				case 'admin_start':
					switch ($messageText) {
						case 'Вопросы':
							User::updateStage('admin_questions');
							Action::text($texts['admin_questions'], $buttons['admin_questions']);
							break;
						case 'Рейтинг':
							User::updateStage('admin_rate');
							Action::text($texts['admin_rate'], $buttons['admin_rate']);
							break;
						case 'Перезапустить день':
							Questions::tranferQuestionsInTheArchive();
							Users::updatePassTodayQuestions();
							Users::setTodayRateToZero();
							Action::text("День успешно перезагружен, все вопросы отправлены в архив", $buttons['admin_start']);
							User::updateStage('admin_start');
							break;
						case 'Рассылка':
							Users::mailing();
							Action::text("Вопросы успешно отправлены участникам", $buttons['admin_start']);
							User::updateStage('admin_start');
							break;
					}
					break;

				case 'admin_questions':
					switch ($messageText) {
						case 'Группы':
							User::updateStage('admin_groups');
							Action::text(Groups::getAllGroupsNames(), $buttons['admin_groups']);
							break;

						case 'Добавить вопрос':
							$newQuestionObject = [];
							User::updateStage('admin_add_question_text');
							Action::text($texts['admin_add_question_text']);
							break;

						case 'Показать ежедневные вопросы':
							Action::text(Questions::showAllQuestions(), $buttons['admin_questions']);
							break;

						case 'Показать вопросы из архива':
							Action::text(Questions::getQuestionsFromArchiveText(), $buttons['admin_questions']);
							break;

						case 'Удалить последний вопрос':
							Questions::deleteLastQuestion();
							Action::text("Последний вопрос успешно удалён", $buttons['admin_questions']);
							break;

						case 'Назад':
							Action::text($texts['admin_start'], $buttons['admin_start']);
							User::updateStage('admin_start');
							break;
					}
					break;

				case 'admin_groups':
					switch ($messageText) {
						case 'Назад':
							Action::text($texts['admin_start'], $buttons['admin_start']);
							User::updateStage('admin_start');
							break;
						
						case 'Добавить группу':
							Action::text($texts['admin_add_group_name']);
							User::updateStage('admin_add_group_name');
							break;	

						case 'Удалить группу':
							Action::text($texts['admin_delete_group_name'], Groups::getAllGroupsButtons());
							User::updateStage('admin_delete_group_name');
							break;

						case 'Добавить вопрос в группу':
							Action::text($texts['admin_add_question_name'], Groups::getAllGroupsButtons());
							User::updateStage('admin_add_question_name');
							break;

						case 'Удалить вопрос из группы':
							Action::text($texts['admin_delete_question_name'], Groups::getAllGroupsButtons());
							User::updateStage('admin_delete_question_name');
							break;

						default:
							# code...
							break;
					}
					break;

				case 'admin_delete_question_name':
					Groups::$currentGroupToDeleteQuestion = $messageText;
					Action::text($texts['admin_delete_question_number']);
					User::updateStage('admin_delete_question_number');
					break;

				case 'admin_delete_question_number':
					Groups::deleteQuestionInGroup($messageText);
					User::updateStage('admin_groups');
					Action::text(Groups::getAllGroupsNames(), $buttons['admin_groups']);
					break;

				case 'admin_add_question_name':
					Groups::$currentGroupToAddQuestion = $messageText;
					Action::text($texts['admin_add_question_number']);
					User::updateStage('admin_add_question_number');
					break;

				case 'admin_add_question_number':
					Groups::pushQuestionInGroup($messageText);
					User::updateStage('admin_groups');
					Action::text(Groups::getAllGroupsNames(), $buttons['admin_groups']);
					break;

				case 'admin_delete_group_name':
					Groups::deleteGroup($messageText);
					User::updateStage('admin_groups');
					Action::text(Groups::getAllGroupsNames(), $buttons['admin_groups']);
					break;

				case 'admin_add_group_name':
					Groups::addGroup($messageText);
					User::updateStage('admin_groups');
					Action::text(Groups::getAllGroupsNames(), $buttons['admin_groups']);
					break;

				case 'admin_add_question_text':
					$newQuestionObject['text'] = $messageText;
					User::updateStage('admin_add_question_answers');
					Action::text($texts['admin_add_question_answers']);
					break;

				case 'admin_add_question_answers':
					$newQuestionObject['answers'] = Questions::makeAnswerArray($messageText);
					User::updateStage('admin_add_question_rightAnswer');
					Action::text($texts['admin_add_question_rightAnswer']);
					break;

				case 'admin_add_question_rightAnswer':
					$newQuestionObject['rightAnswer'] = $messageText;
					Questions::addNewQuestion($newQuestionObject);
					Action::text("Вопрос успешно добавлен в систему", $buttons['admin_questions']);
					User::updateStage('admin_questions');
					break;

				case 'admin_rate':
					switch ($messageText) {
						case 'Показать рейтинг':
							Action::text(Rate::showRate(), $buttons['admin_rate']);
							break;
						case 'Обнулить рейтинг':
							Rate::resetRate();
							Action::text("Рейтинг успешно обнулен", $buttons['admin_rate']);
							break;

						case 'Назад':
							Action::text($texts['admin_start'], $buttons['admin_start']);
							User::updateStage('admin_start');
							break;
					}
					break;


				
				

				case 'name_undefined':
					User::updateNick($messageText);
					User::updateStage('start');
					Action::text("Привет, " . $user['nick'] . "! " . $texts['start'] , $buttons['start']);
					break;

				case 'start':
					switch ($messageText) {
						case 'Архив вопросов':
							User::updateStage('archive_start');
							Action::text($texts['archive_start'] , $buttons['archive_start']);
							break;
						case 'Информация обо мне':
							User::updateStage('information');
							Action::text(User::getInformation() , $buttons['information']);
							break;
						case 'Вопросы на сегодня':
							User::updatelastQuestionWeAsked(0);
							User::updateStage('questions_today');
							Action::text($todayQuestions[0]['text'], $todayQuestions[0]['answers']);
							break;
					}
					break;

				case 'archive_start':
					switch ($messageText) {
						case 'Вопросы по темам':
							Action::text($texts['archive_questions_by_theme'], array_merge(Groups::getAllGroupsButtons(), ["Назад"]));
							User::updateStage('archive_questions_by_theme');
							break;

						case 'Cлучайные вопросы':
							User::updateStage('archive_questions_rand_play');
							$randInd = rand(0, count($archiveQuestionsForRand) - 1);
							Action::text($archiveQuestionsForRand[$randInd]['text'], array_merge($archiveQuestionsForRand[$randInd]['answers'], ["Выйти"]));
							User::updatelastQuestionWeAsked($randInd);
							break;

						case 'Назад':
							if($user['passTodayQuestions']){
								Action::text(User::getTodayStatistics(), $buttons['end']);
								User::updateStage('end');
							} else {
								Action::text("Привет, " . $user['nick'] . ". " . $texts['start'] , $buttons['start']);
								User::updateStage('start');
							}
							break;

						default:
							break;

					}
					break;

				case 'archive_questions_by_theme':
					if($messageText == "Назад"){
						User::updateStage('archive_start');
						Action::text($texts['archive_start'] , $buttons['archive_start']);
					} else {
						User::updateCurrentThemeArray(Groups::getQuestionArrayFromGroup($messageText));
						User::updatelastQuestionWeAsked(0);
						$questionIndex = $user['currentThemeArray'][$user['lastQuestionWeAsked']];
						Action::text($archiveQuestions[$questionIndex]['text'], array_merge($archiveQuestions[$questionIndex]['answers'], ["Выйти"]));
						User::updateStage('archive_questions_by_theme_play');
					}
					break;

				case 'archive_questions_rand_play':
					if($messageText == "Выйти"){
						User::updateStage('archive_start');
						Action::text($texts['archive_start'] , $buttons['archive_start']);
						break;
					} else {
						$answer = Questions::getOpinionAboutTheArchiveRandQuestion($messageText);
						$randInd = rand(0, count($archiveQuestionsForRand) - 1);
						Action::text($answer . $archiveQuestionsForRand[$randInd]['text'], array_merge($archiveQuestionsForRand[$randInd]['answers'], ["Выйти"]));
						User::updatelastQuestionWeAsked($randInd);
						break;
					}

				case 'archive_questions_by_theme_play':
					if($messageText == "Выйти"){
						User::updateStage('archive_start');
						Action::text($texts['archive_start'] , $buttons['archive_start']);
						break;
					} else {
						$answer = Questions::getOpinionAboutTheArchiveQuestion($messageText);
						if($user['lastQuestionWeAsked'] != count($user['currentThemeArray']) - 1){
							$nextQuestion = $user['lastQuestionWeAsked'] + 1;
							User::updatelastQuestionWeAsked($nextQuestion);
							$questionIndex = $user['currentThemeArray'][$user['lastQuestionWeAsked']];
							Action::text($answer . $archiveQuestions[$questionIndex]['text'], array_merge($archiveQuestions[$questionIndex]['answers'], ["Выйти"]));
						} else {
							Action::text($answer . $texts['archive_questions_by_theme_end'], $buttons['archive_questions_by_theme_end']);
							User::updateStage('archive_questions_by_theme_end');
						}
					}
					break;

				case 'archive_questions_by_theme_end':
					switch ($messageText) {
						case 'Назад':
							User::updateStage('archive_start');
							Action::text($texts['archive_start'] , $buttons['archive_start']);
							break;
						default:
							# code...
							break;
					}
					break;

				case 'information':
					switch ($messageText) {
						case 'Продолжить':
							User::updateStage('start');
							Action::text("Привет, " . $user['nick'] . "! " . $texts['start'] , $buttons['start']);
							break;
						case 'Изменить ник':
							User::updateStage('nickUpdate');
							Action::text($texts['nickUpdate']);
							break;
					}
					break;

				case 'nickUpdate':
					User::updateNick($messageText);
					User::updateStage('information');
					Action::text(User::getInformation() , $buttons['information']);
					break;

				case 'questions_today':
					$answer = Questions::getOpinionAboutTheTodayQuestion($messageText);
					if($user['lastQuestionWeAsked'] != count($todayQuestions) - 1){
						$nextQuestion = $user['lastQuestionWeAsked'] + 1;
						User::updatelastQuestionWeAsked($nextQuestion);
						Action::text($answer . $todayQuestions[$nextQuestion]['text'], $todayQuestions[$nextQuestion]['answers']);
					} else {
						Action::text($answer . User::getTodayStatistics(), $buttons['end']);
						User::updateStage('end');
					}
					break;

				case 'end':
					User::updatePassTodayQuestions();
					switch ($messageText) {
						case 'Архив вопросов':
							User::updateStage('archive_start');
							Action::text($texts['archive_start'] , $buttons['archive_start']);
							break;
						case 'Оставить отзыв':
							User::updateStage('write_review');
							Action::text($texts['write_review']);
							break;

						case 'Информация обо мне':
							User::updateStage('informationAfterEnd');
							Action::text(User::getInformation() , $buttons['information']);
							break;
						default:
							# code...
							break;
					}

				case 'informationAfterEnd':
					switch ($messageText) {
						case 'Продолжить':
							Action::text(User::getTodayStatistics(), $buttons['end']);
							User::updateStage('end');
							break;
						case 'Изменить ник':
							User::updateStage('nickUpdateAfterEnd');
							Action::text($texts['nickUpdate']);
							break;
					}
					break;

				case 'nickUpdateAfterEnd':
					User::updateNick($messageText);
					User::updateStage('informationAfterEnd');
					Action::text(User::getInformation() , $buttons['information']);
					break;

				case 'write_review':
					Reviews::addReview($messageText);
					if($user['passTodayQuestions']){
						Action::text("Спасибо за отзыв! Я надеюсь, мы его прочтем \n \n" . User::getTodayStatistics(), $buttons['end']);
						User::updateStage('end');
					} else {
						Action::text("Привет, " . $user['nick'] . ". " . $texts['start'] , $buttons['start']);
						User::updateStage('start');
					}
					break;
			}
		};
	};
?>