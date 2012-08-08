<?
	/**
	 * IRC Robot Class
	 * @author jfreeman
	 * @since 8.7.2012
	 * @version $Id
	 */
error_reporting(E_ALL);
ini_set('display_errors',true); 

	class Bot {
		/**
		 * IRC Server
		 * @var string
		 */
		protected $_server = null;
		/**
		 * IRC Port
		 * @var int
		 */
		protected $_port = null;
		/**
		 * IRC Bot NICK
		 * @var string
		 */
		protected $_nick = null;
		/**
		 * IRC BOT Gecos / Real name
		 * @var string
		 */
		protected $_gecos = null;
		/**
		 * List of channels
		 * @var array
		 */
		protected $_channelsArray = array();
		/**
		 * Socket connection 
		 * @var socket
		 */
		private $_socket = null;
		
		/**
		 * Database interaction layer
		 * @var mysqli handler
		 */
		private $_db = null;
		
		/**
		 * Contains the list of authorized users
		 * @var array
		 */
		protected $_authUserArray = array();

		/**
		 * Verbosity for logging
		 * @var boolean
		 */
		public $verbose = false;
		
		/**
		 * Data string from IRC socket
		 * @var string
		 */
		protected $_data = null;
		/**
		 * Split on spaces to parse easier
		 * @var array
		 */
		protected $_dataArray = array();
		
		public function __construct($configArray = array() ) {
			
			if ( isset($configArray['mysqlHost']) && isset($configArray['mysqlDB'])) {
				$this->_db = new mysqli($configArray['mysqlHost'],$configArray['mysqlUser'],( isset($configArray['mysqlPass']) ? $configArray['mysqlPass'] : ''),$configArray['mysqlDB']);
			}

			if ( array_key_exists('server', $configArray) ) {
				$this->set_server($configArray['server']);
			}
			if ( array_key_exists('port', $configArray) ) {
				$this->set_port($configArray['port']);
			}
			if ( array_key_exists('nick', $configArray) ) {
				$this->set_nick($configArray['nick']) ;
			}
			if ( array_key_exists('gecos', $configArray) ) {
				$this->set_gecos($configArray['gecos']);
			}
			
			if ( array_key_exists('verbose', $configArray)) {
				$this->set_verbose($configArray['verbose']);
			}

		}

		public function set_verbose($verbose) {
			if ( $verbose ) {
				$this->verbose = true;
				return true;
			} else {
				$this->verbose = false;
				return false;
			}
		}
		
		public function get_verbose()  {
			return $this->verbose;
		}
		
		public function set_server($server) {
			if ($server) {
				$this->_server = $server; 
				return true;
			} else {
				$this->_log("Invalid Server Parameter");
				throw new Exception("Invalid Server Parameter");
				return false;
			}
		}

		public function get_server() {
			return $this->_server;
		}

		public function set_port($port) {
			if ($port) {
				$this->_port = $port;
				return true;
			}  else {
				$this->_log("Invalid Port Parameter");				
				throw new Exception("Invalid Port Parameter");
				return false;
			}
		}

		public function set_nick($nick) {
			if ($nick) {
				$this->_nick =  $nick;
				return true;
			} else {
				$this->_log("Invalid Nick Parameter");
				throw new Exception("Invalid Nick Parameter");
				return false;
			}
		}
		public function get_nick() {
			return $this->_nick;
		}
		public function set_gecos($gecos) {
			if ($gecos) {
				$this->_gecos = $gecos;
				return true;
			} else {
				$this->_log("Invalid Gecos Parameter");
				throw new Exception("Invalid Gecos Parameter");
				return false;
			}
		}
		public function get_gecos() {
			return $this->_gecos;
		}

		public function connect() {

				if ( !$this->_server || !$this->_port ) {
					$this->_log("Invalid server / port combination");
					throw new Exception("Invalid server / port combination"); 
				} else {
					 $this->_socket = fsockopen($this->_server, $this->_port) ;
				}
				$this->_login();
				$this->_main();
		}
		protected function _login() {

			$this->_send_data('NICK ' . $this->get_nick() );
			$this->_send_data('USER ' . $this->get_nick() . ' ' . $this->get_server() . ' ' . true . ' :' . $this->get_gecos() );
			$this->_join_channel('#jimi');
		}
		protected function _send_data($cmd, $msg = null) {

			if($msg == null) {

				fputs($this->_socket, $cmd."\r\n");

			} else {

				fputs($this->_socket, $cmd.' '.$msg."\r\n");

			}

		}

		protected function  _join_channel($channel) {

			if ( $channel ) {
				
				if (  !array_key_exists($channel,$this->_channelsArray) ) {
					// join code
					$this->_send_data('JOIN ' . $channel);
					// janky way to do this, need to check that we actually joined the channel
					$this->_channelArray[$channel] = true;
				} else {
					// already in channel
					return false;
				}
			} else {
				return false;
			}
			
		}


		public function disconnect() {

			$this->_send_data('Quit');

			$this->__destruct();
		}
		private function _main() {

			while ( $this->_socket ) {

				$this->_data = trim(fgets($this->_socket, 128));
				$this->_dataArray = explode(' ', $this->_data);
				$this->_log($this->_data);					
			
				$this->_handleCommand();
			}
		}
		
		protected function _handleCommand() {
			
			switch($this->_dataArray[0]) {

				// if we receive a PING request fromt he server
				case 'PING':
					// send the pong response.
					$this->_doPing($this->_dataArray[1]);
					break;
			}
			
			// commands ... 
			if ( isset($this->_dataArray[3])) {

				switch($this->_dataArray[3]) {
					case ':!auth':
						$this->_doAuth($this->_dataArray[0],$this->_dataArray[4],$this->_dataArray[5]);
						break;
					case ':!names':
						$this->_get_names_by_channel($this->_dataArray[4]);
						break;
				}
				// karma ... looking for something folloed by ++
				if ( preg_match('/:(.*)\+\+/', $this->_dataArray[3], $matches)) {
					
					$this->increase_karma_by_nick($matches[1]);
				}
				if ( preg_match('/:(.*)\-\-/', $this->_dataArray[3], $matches)) {
					
					$this->decrease_karma_by_nick($matches[1]);
					
				}
				
				
			}
			
			if ( isset($this->_dataArray[1])) {
				
				switch($this->_dataArray[1]) {
					
					case '353':
						$this->_handleNames();
						break;
					case 'JOIN':
						$this->_handleJoin();
						break;
					case 'PART':
						$this->_handlePart();
						break;
					case 'QUIT':
						break;
				}
				
			}
		}
		
		protected function _doPing($pong) {
			
			$this->_send_data('PONG ' . $pong);
			$this->_log('PONG ' . $pong);
		}
		
		protected function _say($message) {
			
			$this->_sendPrivateMessage($this->_dataArray[2], $message);
			
		}
		
		protected function _sendPrivateMessage($target,$message) {
			
			$this->_send_data('PRIVMSG ' . $target . ' :' . $message);
			
		}
		
		protected function _doAuth($from,$user,$password) {
			
			// check to see if the user is already authorized
			if ( !array_key_exists($from,$this->_authUserArray)) {

				$authUser = $this->_db->query('SELECT * FROM users where username="'.$user.'" AND Password=MD5("'.$password.'")')->num_rows;
				
				//succesful auth
				if ( $authUser ) {
						
					$this->_authUserArray[$from] = true;
					$this->_log($from . " is now authorized");
						
				} else {
					// failed auth, log it
					$this->_log($from . " failed authorization");
				}
				
			} else {
				// do nothing, user already logged in
				$this->_log($from . ' already authorized');
			}
		}
		
		protected function _get_names_by_channel($channel) { 
			
			$this->_send_data('NAMES ' . $channel);
			
		}
		
		protected function _handleNames() {
			
			$channel = $this->_dataArray[4];
			
			$tmp = explode(':', $this->_data);

			$users = explode(' ', $tmp[2]);
			
			foreach ($users as $user) {
				
				$user = str_replace('@','', $user);
				$user = str_replace('+','', $user);

				$this->_channelsArray[$channel]['users'][$user] = true;
			}
		
		}
		protected function _handleJoin() {
			
			
			$dataArray[0] = str_replace(':', '', $this->_dataArray[0]);
			$tmp = explode('!', $this->_dataArray[0]);
			$nick = $tmp[0];
			$channel = $this->_dataArray[2];
			
			$this->_channelsArray[$channel]['users'][$nick] = true;
			
		}

		protected function _handlePart() {
				
				
			$dataArray[0] = str_replace(':', '', $this->_dataArray[0]);
			$tmp = explode('!', $this->_dataArray[0]);
			$nick = $tmp[0];
			$channel = $this->_dataArray[2];
				
			unset($this->_channelsArray[$channel]['users'][$nick]);
				
		}
		public function increase_karma_by_nick($nick) {
			
		}
		public function decrease_karma_by_nick($nick) {
			
		}
		protected function _log($data) {
			
			// only display if we verbosity is turned on
			if ( $this->verbose ) {
				
				echo $data ."\r\n";
			}
		}
	}
