<?php

namespace Budabot\User\Modules;

use Budabot\Core\StopExecutionException;

/**
 * Authors:
 *  - Nadyita
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
	 * @return string[]
	 */
	protected function normalizeBotNames(string $botNames): array {
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
	 */
	public function changeTradebot(string $setting, string $oldValue, string $newValue): void {
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
	 */
	public function isTradebot(string $botName): bool {
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
	 */
	public function receiveRelayMessageExtPrivEvent(\StdClass $eventObj): void {
		if (!$this->isTradebot($eventObj->channel)) {
			return;
		}
		$this->processIncomingTradeMessage($eventObj->channel, $eventObj->message);
		throw new StopExecutionException();
	}

	/**
	 * @Event("msg")
	 * @Description("Relay incoming tells from the tradebots to org/private channel")
	 */
	public function receiveMessageEvent(\StdClass $eventObj): void {
		if (!$this->isTradebot($eventObj->sender)) {
			return;
		}
		$this->processIncomingTradebotMessage($eventObj->sender, $eventObj->message);
		throw new StopExecutionException();
	}

	/**
	 * Relay incoming tell-messages of tradebots to org/priv chat, so we can see errros
	 */
	public function processIncomingTradebotMessage(string $sender, string $message): void {
		$message = "Received message from Tradebot <highlight>$sender<end>: $message";
		$this->chatBot->sendGuild($message, true);
		if ($this->settingManager->get("guest_relay") == 1) {
			$this->chatBot->sendPrivate($message, true);
		}
	}
	
	/**
	 * Relay incoming priv-messages of tradebots to org/priv chat,
	 * but filter out join- and leave-messages of people.
	 */
	public function processIncomingTradeMessage(string $sender, string $message): void {
		// Don't relay join/leave messages
		if (preg_match('/^[A-Z][a-z0-9-]{3,11} has (joined|left) the private channel\./', strip_tags($message))) {
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
	 */
	public function acceptPrivJoinEvent(\StdClass $eventObj): void {
		$sender = $eventObj->sender;
		if (!$this->isTradebot($sender)) {
			return;
		}
		$this->chatBot->privategroup_join($sender);
	}
}
