<?php

namespace Budabot\User\Modules\TRADEBOT_MODULE;

use Budabot\Core\StopExecutionException;
use stdClass;

/**
 * @author Nadyita (RK5) <nadyita@hodorraid.org>
 *
 * @Instance
 */
class TradebotController {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 */
	public $moduleName;

	/**
	 * @var \Budabot\Core\Budabot $chatBot
	 * @Inject
	 */
	public $chatBot;
	
	/**
	 * @var \Budabot\Core\SettingManager $settingManager
	 * @Inject
	 */
	public $settingManager;
	
	/**
	 * @var \Budabot\Core\LoggerWrapper $logger
	 * @Logger
	 */
	public $logger;

	private $botData = [
		'Darknet' => [
			'join' => ['!register', '!autoinvite on'],
			'leave' => ['!autoinvite off', '!unregister'],
		],
		'Lightnet' => [
			'join' => ['register', 'autoinvite on'],
			'leave' => ['autoinvite off', 'unregister'],
		]
	];

	/** @Setup */
	public function setup() {
		$this->settingManager->add(
			$this->moduleName,
			'tradebot',
			"Name of the bot whose channel to join",
			"edit",
			"text",
			"None",
			"None;" . implode(';', array_keys($this->botData)),
			'',
			"mod",
			"tradebot.txt"
		);
		$this->settingManager->add(
			$this->moduleName,
			"tradebot_channel_spam",
			"Showing Tradebot messages in",
			"edit",
			"options",
			"2",
			"Private Channel;Org;Private Channel and Org;Neither",
			"0;1;2;3",
			"mod"
		);

		$this->settingManager->registerChangeListener(
			'tradebot',
			[$this, 'changeTradebot']
		);
	}

	/**
	 * Convert the colon-separated list of botnames into a proper array
	 *
	 * @param string $botNames Colon-separated list of botnames
	 * @return string[]
	 */
	protected function normalizeBotNames($botNames) {
		return array_diff(
			array_map(
				'ucfirst',
				explode(
					';',
					strtolower($botNames)
				)
			),
			['', 'None']
		);
	}

	/**
	 * (un)subscribe from tradebot(s) when they get activated or deactivated
	 *
	 * @param string $setting Name of the setting that gets changed
	 * @param string $oldValue Old value of that setting
	 * @param string $newValue New value of that setting
	 * @return void
	 */
	public function changeTradebot($setting, $oldValue, $newValue) {
		if ($setting !== 'tradebot') {
			return;
		}
		$oldBots = $this->normalizeBotNames($oldValue);
		$newBots = $this->normalizeBotNames($newValue);
		$botsToSignOut = array_diff($oldBots, $newBots);
		$botsToSignUp = array_diff($newBots, $oldBots);
		foreach ($botsToSignOut as $botName) {
			if (array_key_exists($botName, $this->botData)) {
				foreach ($this->botData[$botName]['leave'] as $cmd) {
					$this->logger->logChat("Out. Msg.", $botName, $cmd);
					$this->chatBot->send_tell($botName, $cmd, "\0", AOC_PRIORITY_MED);
					$this->chatBot->privategroup_leave($botName);
				}
			}
		}
		foreach ($botsToSignUp as $botName) {
			if (array_key_exists($botName, $this->botData)) {
				foreach ($this->botData[$botName]['join'] as $cmd) {
					$this->logger->logChat("Out. Msg.", $botName, $cmd);
					$this->chatBot->send_tell($botName, $cmd, "\0", AOC_PRIORITY_MED);
				}
			}
		}
	}

	/**
	 * Check if the given name is one of the configured tradebots
	 *
	 * @param string $botName Name of the bot to check
	 * @return bool
	 */
	public function isTradebot($botName) {
		$tradebotNames = $this->normalizeBotNames($this->settingManager->get('tradebot'));
		foreach ($tradebotNames as $tradebotName) {
			if (preg_match("/^\Q$tradebotName\E\d*$/", $botName)) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * @Event("extPriv")
	 * @Description("Relay messages from the tradebot to org/private channel")
	 *
	 * @param \Budabot\Core\Event $eventObj
	 * @return void
	 * @throws \Budabot\Core\StopExecutionException
	 */
	public function receiveRelayMessageExtPrivEvent(stdClass $eventObj) {
		if (!$this->isTradebot($eventObj->channel)
			|| !$this->isTradebot($eventObj->sender)) {
			return;
		}
		$this->processIncomingTradeMessage($eventObj->channel, $eventObj->message);
		throw new StopExecutionException();
	}

	/**
	 * @Event("msg")
	 * @Description("Relay incoming tells from the tradebots to org/private channel")
	 *
	 * @param \Budabot\Core\Event $eventObj
	 * @return void
	 */
	public function receiveMessageEvent(stdClass $eventObj) {
		if (!$this->isTradebot($eventObj->sender)) {
			return;
		}
		$this->processIncomingTradebotMessage($eventObj->sender, $eventObj->message);
		throw new StopExecutionException();
	}

	/**
	 * Relay incoming tell-messages of tradebots to org/priv chat, so we can see errros
	 *
	 * @param string $sender
	 * @param string $message
	 * @return void
	 */
	public function processIncomingTradebotMessage($sender, $message) {
		$message = "Received message from Tradebot <highlight>$sender<end>: $message";
		$this->chatBot->sendGuild($message, true);
		if ($this->settingManager->get("guest_relay") == 1) {
			$this->chatBot->sendPrivate($message, true);
		}
	}
	
	/**
	 * Relay incoming priv-messages of tradebots to org/priv chat,
	 * but filter out join- and leave-messages of people.
	 *
	 * @param string $sender
	 * @param string $message
	 * @return void
	 */
	public function processIncomingTradeMessage($sender, $message) {
		// Don't relay join/leave messages
		if (!preg_match('/^\[[a-z]+\]/i', strip_tags($message))) {
			return;
		}
		if (in_array($this->settingManager->get("tradebot_channel_spam"), [1, 2])) {
			$this->chatBot->sendGuild($message, true);
		}
		if (in_array($this->settingManager->get("tradebot_channel_spam"), [0, 2])) {
			$this->chatBot->sendPrivate($message, true);
		}
	}
	
	/**
	 * @Event("extJoinPrivRequest")
	 * @Description("Accept private channel join invitation from the trade bots")
	 *
	 * @param \Budabot\Core\Event $eventObj
	 * @return void
	 */
	public function acceptPrivJoinEvent(stdClass $eventObj) {
		$sender = $eventObj->sender;
		if (!$this->isTradebot($sender)) {
			return;
		}
		$this->chatBot->privategroup_join($sender);
	}
}
