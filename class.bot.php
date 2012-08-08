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
				throw new Exception("Invalid Port Parameter");
				return false;
			}
		}

		public function set_nick($nick) {
			if ($nick) {
				$this->_nick =  $nick;
				return true;
			} else {
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
				throw new Exception("Invalid Gecos Parameter");
				return false;
			}
		}
		public function get_gecos() {
			return $this->_gecos;
		}

		public function connect() {

				if ( !$this->_server || !$this->_port ) {
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
			$this->_join_channel('#testes');
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

				$data = fgets($this->_socket, 128);

				$data = trim($data);
				
				if ( $this->verbose ) {

					$this->_log($data);					
				}
				
				$this->_handleCommand($data);
			}
		}
		
		protected function _handleCommand($data) {
			$dataArray = explode(' ', $data);
			
			// if we receive a PING request fromt he server
			if ($dataArray[0] == 'PING') {
				// send the pong response.
				$this->_doPing($dataArray[1]);
			}
			

			if ( isset($dataArray[3]) &&  $dataArray[3] == ':!auth') {
				// username password
				$this->_doAuth($dataArray[0],$dataArray[4],$dataArray[5]);
			}
			
		}
		
		protected function _doPing($pong) {
			
			$this->_send_data('PONG ' . $pong);
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
		
		protected function _log($data) {
			
			// only display if we verbosity is turned on
			if ( $this->verbose ) {
				
				echo $data ."\r\n";
			}
		}
	}
