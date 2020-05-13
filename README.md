# Trade-bot-module for Budabot and derivates

## Purpose

The purpose of this module is to relay all messages from trade bots into your org- and/or private chat. This has 3 benefits:

1. You will receive messages instantly. Depending on the amount of people online, this means up to 5 minutes earlier.
2. You will remove strain from the trade bots themselves.
3. You don't need to join the trade bots with every single character.

## Installation

1. Go into your bot's directory
2. `git clone -d extras/TRADEBOT_MODULE https://github.com/Nadyita/TRADEBOT_MODULE.git`
3. Restart your bot.
4. Send a `config mod TRADEBOT_MODULE enable all` to your bot.

## Configuration

By default, your bot won't join any trade bots (e.g. Darknet), unless you specifically tell it to by setting the `tradebot` setting to a semicolon-separated list of all trade bots you want to join.

On the time of writing this, the only active trade bot is `Darknet`, so sending your bot a `settings save tradebot Darknet` will make your bot register itself on Darknet and turn on auto-invites. That's all you need to do.
If you want to turn off the relay, just send `settings save tradebot None` and it will automatically unsubscribe.
