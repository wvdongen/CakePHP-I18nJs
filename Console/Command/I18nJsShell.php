<?php

/**
 * I18n JS Shell.
 *
 * Enhances the existing I18n Shell with JS support.
 *
 * @link http://www.dongit.nl
 * @author Wouter S. van Dongen
 */
App::uses('I18nShell', 'Console/Command');

class I18nJsShell extends I18nShell {

	/**
	 * Contains tasks to load and instantiate
	 *
	 * @var array
	 */
	public $tasks = array('I18nJs.CreateJs', 'I18nJs.ExtractJs');

	/**
	 * Override main() for help message hook
	 *
	 * @return void
	 */
	public function main() {
		$this->out(__d('cake_console', '<info>I18nJs Shell</info>'));
		$this->hr();
		$this->out(__d('cake_console', '[C]reate JS file(s) from .po file(s)'));
		$this->out(__d('cake_console', '[E]xtract .pot file from sources'));
		$this->out(__d('cake_console', '[H]elp'));
		$this->out(__d('cake_console', '[Q]uit'));

		$choice = strtolower($this->in(__d('cake_console', 'What would you like to do?'), array('C', 'E', 'H', 'Q')));
		switch ($choice) {
			case 'c':
				$this->CreateJs->execute();
				break;
			case 'e':
				$this->ExtractJs->execute();
				break;
			case 'h':
				$this->out($this->OptionParser->help());
				break;
			case 'q':
				exit(0);
				break;
			default:
				$this->out(__d('cake_console', 'You have made an invalid selection. Please choose a command to execute by entering C, E, H, or Q.'));
		}
		$this->hr();
		$this->main();
	}

	/**
	 * Get and configure the Option parser
	 *
	 * @return ConsoleOptionParser
	 */
	public function getOptionParser() {
		$parser = Shell::getOptionParser();
		return $parser->description(
				__d('cake_console', 'I18nJS Shell generates a .pot file with JS translations and generates JS file(s) from .po file(s).')
			)->addSubcommand('extract_js', array(
				'help' => __d('cake_console', 'Extract JS translatable strings and create I18nJS .pot file'),
				'parser' => $this->ExtractJs->getOptionParser()
			))->addSubcommand('create_js', array(
				'help' => __d('cake_console', 'Create JS translation files from .po file(s)'),
				'parser' => $this->CreateJs->getOptionParser()
		));
	}

}
