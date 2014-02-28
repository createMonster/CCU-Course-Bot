<?php
	require_once('simple_html_dom.php');
	declare(ticks = 1);
	date_default_timezone_set('Asia/Taipei');

	### 課程訊息設置 ###
	$useracc	= "";		# 帳號
	$userpass 	= "";		# 密碼
	$courseID 	= "7304007";		# 課程編號
	$classNum 	= "01";				# 班別
	$depID 		= "I001";			# 系所編號 (I001 通識 , 4104 資工系, F000 體育)
	$grade 		= "3";				# 年級/領域
	$pageNum 	= 3;				# 在第幾頁
	$point 		= 3;				# 學分數 下面列出目前已測學分數

	############# 學分數對照 ################
	# [通識(I001)]	= 3
	#
	# [體育(F000)]
	#	一、二年級 	= 1 
	#	三年級 		= 2
	#
	# [一般系所] 
	#	學分數-1 (列表上寫 3 學分 -> $point = 2, 只測過資工系所)
	#	資工系(4104) 資工所(4106)
	#
	# [軍訓(V000)]	= 2
	#
	# [語言中心] 不想看 有一級主管我怕怕
	#########################################
	
	### 擬人化 ###
	# 擬人點頁面 1:ON/0:OFF
	$personification = 0; 

	# 隨機間隔時間 (second)
	$randMin = 10; 	# 最短
	$randMax = 15;	# 最長

	# Other var
	$failCount 	= 0;						# 記錄抓取失敗次數
	$filename 	= "log_".date('Y-m-d_H-i-s')."_.txt";	# Log 名稱
	$pageResult = "";
	$shutdownReason = "";

	function logout($ch, $sessionID) {
		global $ch, $sessionID, $filename, $shutdownReason;

		$optionsLogout = array(
			CURLOPT_URL 			=> "http://kiki.ccu.edu.tw/~ccmisp06/cgi-bin/class_new/logout.php?session_id=".$sessionID,
		 	CURLOPT_HEADER 			=> false,
		  	CURLOPT_POST 			=> false,
		  	CURLOPT_USERAGENT		=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:27.0) Gecko/20100101 Firefox/27.0",
		  	CURLOPT_FOLLOWLOCATION 	=> true,
		  	CURLOPT_RETURNTRANSFER 	=> true,
		  	CURLOPT_COOKIEJAR 		=> 'cookie',
		  	CURLOPT_COOKIEFILE 		=> 'cookie');
		curl_setopt_array($ch, $optionsLogout);

		echo "三秒後登出\n";
		sleep(3);
		$pageResult = curl_exec($ch);

		if (preg_match("/成功登出/", $pageResult) == 1) {
			echo "登出成功 ".$sessionID."\n";
			$shutdownReason .= date('Y-m-d H:i:s')."	登出成功 ".$sessionID."\n";
		} else {
			echo "登出失敗 ".$sessionID."\n";
			$shutdownReason .= date('Y-m-d H:i:s')."	登出失敗 ".$sessionID."\n";
		}
	}

	### 初始化連線 ###
	$ch = curl_init();
	$firstTime = 1;

	### signal handler, 強制退出時會記得等出 >.^ ###
	function signal_handler($signal) {
		global $ch, $sessionID, $filename, $shutdownReason;
        
        print "接收到強制關閉訊號，進行登出\n";
        $shutdownReason .= date('Y-m-d H:i:s')."	接收到強制關閉訊號，進行登出\n";
        logout($ch, $sessionID);
		curl_close($ch);
        exit;
    }

	echo "登錄 signal handler...\n";
	pcntl_signal(SIGINT, "signal_handler");
	pcntl_signal(SIGTERM, "signal_handler");
	echo "完成登錄 signal handler...\n";

	if($personification == 1) {
		echo "擬人化 - on\n";
		$shutdownReason .= date('Y-m-d H:i:s')."	擬人化 - on\n";
	} else { 
		echo "擬人化 - off\n";
		$shutdownReason .= date('Y-m-d H:i:s')."	擬人化 - off\n";
	}

	### 程式結束會留下 log ###
	register_shutdown_function('CatchFatalError');

	function CatchFatalError() {
		global $pageResult, $filename, $shutdownReason;
		file_put_contents($filename, $shutdownReason, FILE_APPEND);
		file_put_contents($filename, $pageResult, FILE_APPEND);
		exit(1);
	}

	### 登入 ###
	$optionsLogin = array(
		CURLOPT_URL 			=> "http://kiki.ccu.edu.tw/~ccmisp06/cgi-bin/class_new/bookmark.php",
	 	CURLOPT_HEADER 			=> false,
	  	CURLOPT_POST 			=> true,
	  	CURLOPT_POSTFIELDS 		=> "version=0&id=".$useracc."&password=".$userpass."&term=on",
	  	CURLOPT_USERAGENT		=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:27.0) Gecko/20100101 Firefox/27.0",
	  	CURLOPT_FOLLOWLOCATION 	=> true,
	  	CURLOPT_RETURNTRANSFER 	=> true,
	  	CURLOPT_COOKIEJAR 		=> 'cookie',
	  	CURLOPT_COOKIEFILE 		=> 'cookie');
	curl_setopt_array($ch, $optionsLogin);

	echo "登入中...\n";
	$pageResult = curl_exec($ch);

	### 確認登入狀況，取得 session ID 後開始加選 ###
	$sessionID_result = array();
	if (preg_match("/session_id=(.{36})/", $pageResult, $sessionID_result) == 0) {
		echo "登入失敗.\n";
		$shutdownReason .= date('Y-m-d H:i:s')."	登入失敗\n";
		curl_close($ch);
	} else {
		# Login Confirmed
		$success = 0;

		# Fetch Session ID
		$sessionID = $sessionID_result[1];
		echo "登入成功，本次登入 session ID: ".$sessionID."\n";
		$shutdownReason .= date('Y-m-d H:i:s')."	登入成功，本次登入 session ID: ".$sessionID."\n";

		### 擬人化進入指定頁面 ###
		if ($personification == 1) {
			echo "加選頁面\n";
			$optionsChangePage = array(
				CURLOPT_URL 			=> "http://kiki.ccu.edu.tw/~ccmisp06/cgi-bin/class_new/Add_Course00.cgi?session_id=".$sessionID,
			 	CURLOPT_HEADER 			=> false,
			  	CURLOPT_POST 			=> false,
			  	CURLOPT_USERAGENT		=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:27.0) Gecko/20100101 Firefox/27.0",
			  	CURLOPT_FOLLOWLOCATION 	=> true,
			  	CURLOPT_RETURNTRANSFER 	=> true,
			  	CURLOPT_COOKIEJAR 		=> 'cookie',
			  	CURLOPT_COOKIEFILE 		=> 'cookie');
			curl_setopt_array($ch, $optionsChangePage);
			curl_exec($ch);
			sleep(3);

			for($i = 0; $i < $pageNum-1; $i++) {
				echo "進入第 ".($i+1)." 頁\n";
				$optionsChangePage = array(
					CURLOPT_URL 			=> "http://kiki.ccu.edu.tw/~ccmisp06/cgi-bin/class_new/Add_Course01.cgi",
				 	CURLOPT_HEADER 			=> false,
				  	CURLOPT_POST 			=> true,
				  	CURLOPT_POSTFIELDS 		=> 'session_id='.$sessionID.'&dept='.$depID.'&grade='.$grade.'&cge_cate=&cge_subcate=&SelectTag=0&page='.$i.'&e=0',
				  	CURLOPT_USERAGENT		=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:27.0) Gecko/20100101 Firefox/27.0",
				  	CURLOPT_FOLLOWLOCATION 	=> true,
				  	CURLOPT_RETURNTRANSFER 	=> true,
				  	CURLOPT_COOKIEJAR 		=> 'cookie',
				  	CURLOPT_COOKIEFILE 		=> 'cookie');
				curl_setopt_array($ch, $optionsChangePage);
				curl_exec($ch);
				sleep(2);
			}
		}

		### 開始加選 ###
		while( $success == 0 ) {
			global $pageResult;

			$pageResult = "";
			file_put_contents($filename, $shutdownReason, FILE_APPEND);
			$shutdownReason = "";
			### 進入目標頁面 ###
			#
			# ## ARGUMENT ##
			# dept 		系所 (4104 CSIE, I001 通識)
			# grade 	年級/領域
			# page 		頁數 (page1 = 0, page2 = 1, ...)
			#
			$optionsChangePage = array(
				CURLOPT_URL 			=> "http://kiki.ccu.edu.tw/~ccmisp06/cgi-bin/class_new/Add_Course01.cgi",
			 	CURLOPT_HEADER 			=> false,
			  	CURLOPT_POST 			=> true,
			  	CURLOPT_POSTFIELDS 		=> 'session_id='.$sessionID.'&dept='.$depID.'&grade='.$grade.'&cge_cate=&cge_subcate=&SelectTag=0&page='.($pageNum-1).'&e=0',
			  	CURLOPT_USERAGENT		=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:27.0) Gecko/20100101 Firefox/27.0",
			  	CURLOPT_FOLLOWLOCATION 	=> true,
			  	CURLOPT_RETURNTRANSFER 	=> true,
			  	CURLOPT_COOKIEJAR 		=> 'cookie',
			  	CURLOPT_COOKIEFILE 		=> 'cookie');
			curl_setopt_array($ch, $optionsChangePage);

			$pageResult = curl_exec($ch);

			echo "進入目標頁面\n";
			$shutdownReason .= date('Y-m-d H:i:s')."	進入目標頁面\n";

			if (preg_match("/科目列表/", $pageResult) == 0) {
				if ($failCount > 5) {
					echo "多次抓取列表失敗，登出。";
					$shutdownReason .= date('Y-m-d H:i:s')."	多次抓取列表失敗，登出。\n";
					$success = 1;
					$firstTime = 0;
					logout($ch, $sessionID);
					curl_close($ch);
					exit;
				}
				$failCount++;
				$sleepTime = rand($randMin, $randMax);
				echo '列表抓取失敗，'.$sleepTime.'秒後重試'."\n";
				$shutdownReason .= date('Y-m-d H:i:s').'	列表抓取失敗，'.$sleepTime.'秒後重試'."\n";
				sleep($sleepTime);
				continue;
			}

			### 確認剩餘人數及課程名稱 ###
			$html = new simple_html_dom();
			$html->load($pageResult);

			if ($firstTime == 1) {
				echo "第一次使用，抓取課程位置\n";

				$list = $html->find('form', 0)->find('tr', 0)->find('th', 0)->find('table', 0);

				$courseRow = -1;
				$count = 0;
				foreach($list->find('tr') as $ul) {
					$course_name = $ul->find('th', 3)->find('font', 0);
					$name = $course_name->innertext;

					if (preg_match("/".$courseID."/", $name) == 1) {
						$courseRow = $count;
						echo "已找到課程位置"."(".$courseRow.")\n";
						$firstTime = 0;

						### 檢查是否衝堂 ###
						$optionsBook = array(
						CURLOPT_URL 			=> "http://kiki.ccu.edu.tw/~ccmisp06/cgi-bin/class_new/Add_Course01.cgi",
					 	CURLOPT_HEADER 			=> false,
					  	CURLOPT_POST 			=> true,
					  	CURLOPT_POSTFIELDS 		=> 'session_id='.$sessionID.'&dept='.$depID.'&grade='.$grade.'&cge_cate=&cge_subcate=&page='.$pageNum.'&e=0&SelectTag=1&'.$courseID.'_'.$classNum.'='.$point.'&course='.$courseID.'_'.$classNum,
					  	CURLOPT_USERAGENT		=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:27.0) Gecko/20100101 Firefox/27.0",
					  	CURLOPT_FOLLOWLOCATION 	=> true,
					  	CURLOPT_RETURNTRANSFER 	=> true,
					  	CURLOPT_COOKIEJAR 		=> 'cookie',
					  	CURLOPT_COOKIEFILE 		=> 'cookie');
						curl_setopt_array($ch, $optionsBook);

						$pageResult = curl_exec($ch);

						if (preg_match("/衝堂/", $pageResult) == 1) {
							echo '##### 錯誤，衝堂 ######'."\n";
							$shutdownReason .= date('Y-m-d H:i:s')."	錯誤，衝堂\n";
							$success = 1;
							logout($ch, $sessionID);
							curl_close($ch);
							exit;
						}
					}

					$count++;
				}

				if($courseRow == -1){
					echo "錯誤，抓到不正確課程位置 (1)\n";
					$shutdownReason .= date('Y-m-d H:i:s')."	錯誤，抓到不正確課程位置 (1)\n";
					$success = 1;
					$firstTime = 0;
					logout($ch, $sessionID);
					curl_close($ch);
					exit;
				}

				$firstTime = 0;
			}

						
			$course_name = $html->find('form', 0)->find('tr', 0)->find('th', 0)->find('table', 0)->find('tr', $courseRow)->find('th', 3)->find('font', 0);
			$name = $course_name->innertext;
			$name = preg_replace("/<br>/", " ", $name);

			if (preg_match("/".$courseID."/", $name) == 0) {
				echo "錯誤，抓到不正確課程"."(".$name.")\n";
				$shutdownReason .= date('Y-m-d H:i:s')."	錯誤，抓到不正確課程"."(".$name.")\n";
				$success = 1;
				logout($ch, $sessionID);
				curl_close($ch);
				exit;
			}
			# 名額
			$course_slot = $html->find('form', 0)->find('tr', 0)->find('th', 0)->find('table', 0)->find('tr', $courseRow)->find('th', 2);
			$slot = $course_slot->plaintext;

			echo ' - 課程名稱：'.$name."\n";
			echo ' - 名額	：'.$slot."\n";

			$shutdownReason .= date('Y-m-d H:i:s').'	課程名稱：'.$name.', 名額：'.$slot."\n";

			if ($slot == 0) {
				$sleepTime = rand($randMin, $randMax);
				echo '額滿，'.$sleepTime.' 秒後重試'."\n";
				$shutdownReason .= date('Y-m-d H:i:s').'	額滿，'.$sleepTime.' 秒後重試'."\n";
				sleep($sleepTime);
				if ($personification == 1) {
					echo "進入第 ".($pageNum+1)." 頁\n";
					$optionsChangePage = array(
						CURLOPT_URL 			=> "http://kiki.ccu.edu.tw/~ccmisp06/cgi-bin/class_new/Add_Course01.cgi",
					 	CURLOPT_HEADER 			=> false,
					  	CURLOPT_POST 			=> true,
					  	CURLOPT_POSTFIELDS 		=> 'session_id='.$sessionID.'&dept='.$depID.'&grade='.$grade.'&cge_cate=&cge_subcate=&SelectTag=0&page='.$pageNum.'&e=0',
					  	CURLOPT_USERAGENT		=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:27.0) Gecko/20100101 Firefox/27.0",
					  	CURLOPT_FOLLOWLOCATION 	=> true,
					  	CURLOPT_RETURNTRANSFER 	=> true,
					  	CURLOPT_COOKIEJAR 		=> 'cookie',
					  	CURLOPT_COOKIEFILE 		=> 'cookie');
					curl_setopt_array($ch, $optionsChangePage);
					curl_exec($ch);
					sleep(1);
				}
			} else {
				echo '##### 目前有名額！'."\n";
				$shutdownReason .= date('Y-m-d H:i:s')."	目前有名額\n";
				# 加選
				#
				# ## ARGUMENT ##
				# dept 		系所 (4104 CSIE, I001 通識)
				# grade 	年級/領域
				# page 		頁數 (page1 = 1, page2 = 2, ...)
				# course 	課程編號
				#
				$optionsBook = array(
					CURLOPT_URL 			=> "http://kiki.ccu.edu.tw/~ccmisp06/cgi-bin/class_new/Add_Course01.cgi",
				 	CURLOPT_HEADER 			=> false,
				  	CURLOPT_POST 			=> true,
				  	CURLOPT_POSTFIELDS 		=> 'session_id='.$sessionID.'&dept='.$depID.'&grade='.$grade.'&cge_cate=&cge_subcate=&page='.$pageNum.'&e=0&SelectTag=1&'.$courseID.'_'.$classNum.'='.$point.'&course='.$courseID.'_'.$classNum,
				  	CURLOPT_USERAGENT		=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:27.0) Gecko/20100101 Firefox/27.0",
				  	CURLOPT_FOLLOWLOCATION 	=> true,
				  	CURLOPT_RETURNTRANSFER 	=> true,
				  	CURLOPT_COOKIEJAR 		=> 'cookie',
				  	CURLOPT_COOKIEFILE 		=> 'cookie');
				curl_setopt_array($ch, $optionsBook);

				$pageResult = curl_exec($ch);

				if (preg_match("/已滿/", $pageResult) == 0) {
					echo '##### 成功加選課程 ######'."\n";
					$shutdownReason .= date('Y-m-d H:i:s')."	成功加選課程\n";
					$success = 1;
					logout($ch, $sessionID);
					curl_close($ch);
				} else {
					$sleepTime = rand($randMin, $randMax);
					echo '晚一步，'.$sleepTime.'秒後重試'."\n";
					$shutdownReason .= date('Y-m-d H:i:s').'	晚一步，'.$sleepTime.'秒後重試'."\n";
					sleep($sleepTime);
					if ($personification == 1) {
						echo "進入第 ".($pageNum+1)." 頁\n";
						$optionsChangePage = array(
							CURLOPT_URL 			=> "http://kiki.ccu.edu.tw/~ccmisp06/cgi-bin/class_new/Add_Course01.cgi",
						 	CURLOPT_HEADER 			=> false,
						  	CURLOPT_POST 			=> true,
						  	CURLOPT_POSTFIELDS 		=> 'session_id='.$sessionID.'&dept='.$depID.'&grade='.$grade.'&cge_cate=&cge_subcate=&SelectTag=0&page='.$pageNum.'&e=0',
						  	CURLOPT_USERAGENT		=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:27.0) Gecko/20100101 Firefox/27.0",
						  	CURLOPT_FOLLOWLOCATION 	=> true,
						  	CURLOPT_RETURNTRANSFER 	=> true,
						  	CURLOPT_COOKIEJAR 		=> 'cookie',
						  	CURLOPT_COOKIEFILE 		=> 'cookie');
						curl_setopt_array($ch, $optionsChangePage);
						curl_exec($ch);
						sleep(1);
					}
				}
			}
		} // Success while
	}
?>