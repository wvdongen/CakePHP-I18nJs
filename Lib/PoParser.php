<?php

namespace Sepia;

/**
 *    Copyright (c) 2012 Raúl Ferràs raul.ferras@gmail.com
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions
 *    are met:
 *    1. Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *    3. Neither the name of copyright holders nor the names of its
 *       contributors may be used to endorse or promote products derived
 *       from this software without specific prior written permission.
 *
 *    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 *    ''AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
 *    TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *    PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL COPYRIGHT HOLDERS OR CONTRIBUTORS
 *    BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 *    CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 * https://github.com/raulferras/PHP-po-parser
 *
 * Class to parse .po file and extract its strings.
 *
 * @method array headers() deprecated
 * @method null update_entry($original, $translation = null, $tcomment = array(), $ccomment = array()) deprecated
 * @method array read($filePath) deprecated
 * @version 3.0.4
 */
class PoParser {

	protected $entries = array();
	protected $headers = array();

	/**
	 * Reads and parses strings of a .po file.
	 *
	 * @param string $filePath
	 * @throws \Exception .
	 * @return Array. List of entries found in .po file.
	 */
	public function parse($filePath) {
		if (empty($filePath)) {
			throw new \Exception('PoParser: Input File not defined.');
		}
		elseif (file_exists($filePath) === false) {
			throw new \Exception('PoParser: Input File does not exists: "' . htmlspecialchars($filePath) . '"');
		}
		elseif (is_readable($filePath) === false) {
			throw new \Exception('PoParser: File is not readable: "' . htmlspecialchars($filePath) . '"');
		}


		$handle = fopen($filePath, 'r');
		$headers = array();
		$hash = array();
		$entry = array();
		$justNewEntry = false; // A new entry has been just inserted.
		$firstLine = true;
		$lastObsoleteKey = null; // Used to remember last key in a multiline obsolete entry.
		$state = null;

		while (!feof($handle)) {
			$line = trim(fgets($handle));

			if ($line === '') {
				if ($justNewEntry) {
					// Two consecutive blank lines
					continue;
				}

				if ($firstLine) {
					$firstLine = false;
					if (self::isHeader($entry)) {
						array_shift($entry['msgstr']);
						$headers = $entry['msgstr'];
					}
					else {
						$hash[] = $entry;
					}
				}
				else {
					// A new entry is found!
					$hash[] = $entry;
				}

				$entry = array();
				$state = null;
				$justNewEntry = true;
				$lastObsoleteKey = null;
				continue;
			}

			$justNewEntry = false;
			$split = preg_split('/\s+/ ', $line, 2);
			$key = $split[0];
			$data = isset($split[1]) ? $split[1] : null;

			switch ($key) {
				// Flagged translation
				case '#,':
					$entry['fuzzy'] = in_array('fuzzy', preg_split('/,\s*/', $data));
					$entry['flags'] = $data;
					break;

				// # Translator comments
				case '#':
					$entry['tcomment'] = !isset($entry['tcomment']) ? array() : $entry['tcomment'];
					$entry['tcomment'][] = $data;
					break;

				// #. Comments extracted from source code
				case '#.':
					$entry['ccomment'] = !isset($entry['ccomment']) ? array() : $entry['ccomment'];
					$entry['ccomment'][] = $data;
					break;

				// Reference
				case '#:':
					$entry['reference'][] = addslashes($data);
					break;

				// #| Previous untranslated string
				case '#|':
					// Start a new entry
					break;

				// #~ Old entry
				case '#~':
					$entry['obsolete'] = true;

					$tmpParts = explode(' ', $data);
					$tmpKey = $tmpParts[0];

					if ($tmpKey != 'msgid' && $tmpKey != 'msgstr') {
						$tmpKey = $lastObsoleteKey;
						$str = $data;
					}
					else {
						$str = implode(' ', array_slice($tmpParts, 1));
					}

					switch ($tmpKey) {
						case 'msgid':
							$entry['msgid'][] = $str;
							$lastObsoleteKey = $tmpKey;
							break;

						case 'msgstr':
							if ($str == "\"\"") {
								$entry['msgstr'][] = trim($str, '"');
							}
							else {
								$entry['msgstr'][] = $str;
							}
							$lastObsoleteKey = $tmpKey;
							break;

						default:
							break;
					}

					continue 2;

				// context
				// Allows disambiguations of different messages that have same msgid.
				// Example:
				//
                // #: tools/observinglist.cpp:700
				// msgctxt "First letter in 'Scope'"
				// msgid "S"
				// msgstr ""
				//
                // #: skycomponents/horizoncomponent.cpp:429
				// msgctxt "South"
				// msgid "S"
				// msgstr ""
				case 'msgctxt':
				// untranslated-string
				case 'msgid':
				// untranslated-string-plural
				case 'msgid_plural':
					$state = $key;
					$entry[$state][] = $data;
					break;
				// translated-string
				case 'msgstr':
					$state = 'msgstr';
					$entry[$state][] = $data;
					break;

				default:
					if (strpos($key, 'msgstr[') !== false) {
						// translated-string-case-n
						$state = 'msgstr';
						$entry[$state][] = $data;
					}
					else {
						// continued lines
						switch ($state) {
							case 'msgctxt':
							case 'msgid':
							case 'msgid_plural':
								if (is_string($entry[$state])) {
									// Convert it to array
									$entry[$state] = array($entry[$state]);
								}
								$entry[$state][] = $line;
								break;

							case 'msgstr':
								// Special fix where msgid is ""
								if ($entry['msgid'] == "\"\"") {
									$entry['msgstr'][] = trim($line, '"');
								}
								else {
									$entry['msgstr'][] = $line;
								}
								break;

							default:
								throw new \Exception(
								'PoParser: Parse error! Unknown key "' . $key . '" on line ' . $line
								);
						}
					}
					break;
			}
		}
		fclose($handle);

		// add final entry
		if ($state == 'msgstr') {
			$hash[] = $entry;
		}


		// - Cleanup header data
		$this->headers = array();
		foreach ($headers as $header) {
			$this->headers[] = "\"" . preg_replace("/\\n/", "\\n", $this->clean($header)) . "\"";
		}

		// - Cleanup data,
		// - merge multiline entries
		// - Reindex hash for ksort
		$temp = $hash;
		$this->entries = array();
		foreach ($temp as $entry) {
			foreach ($entry as &$v) {
				$or = $v;
				$v = $this->clean($v);
				if ($v === false) {
					// parse error
					throw new \Exception(
					'PoParser: Parse error! poparser::clean returned false on "' . htmlspecialchars($or) . '"'
					);
				}
			}

			if (isset($entry['msgid']) && isset($entry['msgstr'])) {
				$id = $this->getEntryId($entry);
				$this->entries[$id] = $entry;
			}
		}

		return $this->entries;
	}

	/**
	 * Get headers from .po file
	 *
	 * @return array
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * Set new headers
	 *
	 * {code}
	 *  array(
	 *   '"Project-Id-Version: \n"',
	 *   '"Report-Msgid-Bugs-To: \n"',
	 *   '"POT-Creation-Date: \n"',
	 *   '"PO-Revision-Date: \n"',
	 *   '"Last-Translator: none\n"',
	 *   '"Language-Team: \n"',
	 *   '"MIME-Version: 1.0\n"',
	 *   '"Content-Type: text/plain; charset=UTF-8\n"',
	 *  );
	 * {code}
	 *
	 * @param array $newHeaders
	 * @return bool
	 */
	public function setHeaders($newHeaders) {
		if (!is_array($newHeaders)) {
			return false;
		}
		else {
			$this->headers = $newHeaders;
			return true;
		}
	}

	/**
	 * Updates an entry.
	 *
	 * @param string $original Original string to translate.
	 * @param string|array $translation Translated string
	 * @param string|array $tcomment
	 * @param string|array $ccomment
	 * @return null
	 */
	public function updateEntry($original, $translation = null, $tcomment = array(), $ccomment = array()) {
		if (null !== $translation) {
			$this->entries[$original]['fuzzy'] = false;
			$this->entries[$original]['msgstr'] = !is_array($translation) ? array($translation) : $translation;
		}

		if (isset($this->entries[$original]['flags'])) {
			$flags = $this->entries[$original]['flags'];
			$this->entries[$original]['flags'] = str_replace('fuzzy', '', $flags);
		}

		$this->entries[$original]['ccomment'] = !is_array($ccomment) ? array($ccomment) : $ccomment;
		$this->entries[$original]['tcomment'] = !is_array($tcomment) ? array($tcomment) : $tcomment;

		return;
	}

	/**
	 * Call old deprecated methods
	 *
	 * @param $method
	 * @param $args
	 * @return mixed
	 * @throws \Exception
	 */
	public function __call($method, $args) {
		switch ($method) {
			case 'update_entry':
				return call_user_func(array($this, 'updateEntry'), $args);
			case 'headers':
				return call_user_func(array($this, 'getHeaders'), $args);
			case 'read':
				return call_user_func(array($this, 'parse'), $args[0]);
			default:
				throw new \Exception('Not registered called method ' . $method);
		}
	}

	/**
	 * Write entries to a po file.
	 *
	 * @example
	 *        $pofile = new PoParser();
	 *        $pofile->parse('ca.po');
	 *
	 *        // Modify an antry
	 *        $pofile->updateEntry( $msgid, $msgstr );
	 *        // Save Changes back into `ca.po`
	 *        $pofile->write('ca.po');
	 * @param string $filePath
	 * @throws \Exception
	 * @return null
	 */
	public function write($filePath) {
		$handle = @fopen($filePath, "wb");
		if ($handle !== false) {
			if (count($this->headers) > 0) {
				fwrite($handle, "msgid \"\"\n");
				fwrite($handle, "msgstr \"\"\n");
				foreach ($this->headers as $header) {
					fwrite($handle, $header . "\n");
				}
				fwrite($handle, "\n");
			}


			$entriesCount = count($this->entries);
			$counter = 0;
			foreach ($this->entries as $entry) {
				$isObsolete = isset($entry['obsolete']) && $entry['obsolete'];
				$isPlural = isset($entry['msgid_plural']);

				if (isset($entry['tcomment'])) {
					foreach ($entry['tcomment'] as $comment) {
						fwrite($handle, "# " . $comment . "\n");
					}
				}

				if (isset($entry['ccomment'])) {
					foreach ($entry['ccomment'] as $comment) {
						fwrite($handle, '#. ' . $comment . "\n");
					}
				}

				if (isset($entry['reference'])) {
					foreach ($entry['reference'] as $ref) {
						fwrite($handle, '#: ' . $ref . "\n");
					}
				}

				if (isset($entry['flags']) && !empty($entry['flags'])) {
					fwrite($handle, "#, " . $entry['flags'] . "\n");
				}

				if (isset($entry['@'])) {
					fwrite($handle, "#@ " . $entry['@'] . "\n");
				}

				if (isset($entry['msgctxt'])) {
					fwrite($handle, 'msgctxt ' . $this->cleanExport($entry['msgctxt'][0]) . "\n");
				}

				if ($isObsolete) {
					fwrite($handle, "#~ ");
				}

				if (isset($entry['msgid'])) {
					// Special clean for msgid
					if (is_string($entry['msgid'])) {
						$msgid = explode("\n", $entry['msgid']);
					}
					elseif (is_array($entry['msgid'])) {
						$msgid = $entry['msgid'];
					}
					else {
						throw new \Exception('msgid not string or array');
					}

					fwrite($handle, 'msgid ');
					foreach ($msgid as $i => $id) {
						if ($i > 0 && $isObsolete) {
							fwrite($handle, "#~ ");
						}
						fwrite($handle, $this->cleanExport($id) . "\n");
					}
				}

				if (isset($entry['msgid_plural'])) {
					// Special clean for msgid_plural
					if (is_string($entry['msgid_plural'])) {
						$msgidPlural = explode("\n", $entry['msgid_plural']);
					}
					elseif (is_array($entry['msgid_plural'])) {
						$msgidPlural = $entry['msgid_plural'];
					}
					else {
						throw new \Exception('msgid_plural not string or array');
					}

					fwrite($handle, 'msgid_plural ');
					foreach ($msgidPlural as $plural) {
						fwrite($handle, $this->cleanExport($plural) . "\n");
					}
				}

				if (isset($entry['msgstr'])) {
					if ($isPlural) {
						foreach ($entry['msgstr'] as $i => $t) {
							fwrite($handle, "msgstr[$i] " . $this->cleanExport($t) . "\n");
						}
					}
					else {
						foreach ((array) $entry['msgstr'] as $i => $t) {
							if ($i == 0) {
								if ($isObsolete) {
									fwrite($handle, "#~ ");
								}

								fwrite($handle, 'msgstr ' . $this->cleanExport($t) . "\n");
							}
							else {
								if ($isObsolete) {
									fwrite($handle, "#~ ");
								}

								fwrite($handle, $this->cleanExport($t) . "\n");
							}
						}
					}
				}

				$counter++;
				// Avoid inserting an extra newline at end of file
				if ($counter < $entriesCount) {
					fwrite($handle, "\n");
				}
			}

			fclose($handle);
		}
		else {
			throw new \Exception('PoParser: Could not write into file "' . htmlspecialchars($filePath) . '"');
		}
	}

	/**
	 * Prepares a string to be outputed into a file.
	 *
	 * @param string $string The string to be converted.
	 * @return string
	 */
	protected function cleanExport($string) {
		$quote = '"';
		$slash = '\\';
		$newline = "\n";

		$replaces = array(
			"$slash" => "$slash$slash",
			"$quote" => "$slash$quote",
			"\t" => '\t',
		);

		$string = str_replace(array_keys($replaces), array_values($replaces), $string);

		$po = $quote . implode("${slash}n$quote$newline$quote", explode($newline, $string)) . $quote;

		// remove empty strings
		return str_replace("$newline$quote$quote", '', $po);
	}

	/**
	 * Generates the internal key for a msgid.
	 *
	 * @param array $entry
	 * @return string
	 */
	protected function getEntryId(array $entry) {
		if (isset($entry['msgctxt'])) {
			$id = implode(',', (array) $entry['msgctxt']) . '!' . implode(',', (array) $entry['msgid']);
		}
		else {
			$id = implode(',', (array) $entry['msgid']);
		}

		return $id;
	}

	/**
	 * Undos `clean_export` actions on a string.
	 *
	 * @param string|array $x
	 * @return string|array.
	 */
	protected function clean($x) {
		if (is_array($x)) {
			foreach ($x as $k => $v) {
				$x[$k] = $this->clean($v);
			}
		}
		else {
			// Remove double quotes from start and end of string
			if ($x == '') {
				return '';
			}

			if ($x[0] == '"') {
				$x = substr($x, 1, -1);
			}

			$x = stripcslashes($x);
		}

		return $x;
	}

	/**
	 * Checks if entry is a header by
	 *
	 * @param array $entry
	 * @return bool
	 */
	protected static function isHeader(array $entry) {
		if (empty($entry) || !isset($entry['msgstr'])) {
			return false;
		}

		$headerKeys = array(
			'Project-Id-Version:' => false,
			//	'Report-Msgid-Bugs-To:'	=> false,
			//	'POT-Creation-Date:'	=> false,
			'PO-Revision-Date:' => false,
			//	'Last-Translator:'		=> false,
			//	'Language-Team:'		=> false,
			'MIME-Version:' => false,
			//	'Content-Type:'			=> false,
			//	'Content-Transfer-Encoding:' => false,
			//	'Plural-Forms:'			=> false
		);
		$count = count($headerKeys);
		$keys = array_keys($headerKeys);

		$headerItems = 0;
		foreach ($entry['msgstr'] as $str) {
			$tokens = explode(':', $str);
			$tokens[0] = trim($tokens[0], "\"") . ':';

			if (in_array($tokens[0], $keys)) {
				$headerItems++;
				unset($headerKeys[$tokens[0]]);
				$keys = array_keys($headerKeys);
			}
		}
		return ($headerItems == $count) ? true : false;
	}

}
