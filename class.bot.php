<?
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
		private $_socket;

		public function __construct($configArray = array() ) {

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
		}
		protected function _send_data($cmd, $msg = null) {

			if($msg == null) {

				fputs($this->_socket, $cmd."\r\n");

			} else {

				fputs($this->_socket, $cmd.' '.$msg."\r\n");

			}

		}

		protected function  join_channel($channel) {

			if ( $channel ) {
				if (  !array_key_exists($channel,$this->_channelArray) ) {
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

			$this->send_data('Quit');

			$this->__destruct();
		}
		private function _main() {

			while ( $this->_socket ) {

				$data = fgets($this->_socket, 128);

				print $data ."\r\n";
			}
		}
	}
