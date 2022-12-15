<?php

use Spip\Cli\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressHelper;


class ExporterBanque extends Command {
	protected function configure() {
		$this
			->setName('exporter:banque')
			->setDescription('Exporter les ecritures de banques pour la compta')
			->addOption(
				'date',
				null,
				InputOption::VALUE_REQUIRED,
				'Mois de l\'export',
				null
			)
			->addOption(
				'presta',
				null,
				InputOption::VALUE_REQUIRED,
				'Prestataire de paiement',
				null
			)
			->addOption(
				'withoutheaders',
				1,
				InputOption::VALUE_NONE,
				'Pas de headers',
				null
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		global $spip_racine;
		global $spip_loaded;

		$first_day_of_month = date('Y-m-01 00:00:00');
		if ($date_export = $input->getOption('date')) {
			$t = strtotime($date_export);
			if (!$t) {
				$output->writeln("<error>Format de date invalide pour {$date_export}</error>");
				exit(1);
			}
			// il faut poser le first_day_of_month sur le 1er jour du mois suivant que l'on veut
			$t = strtotime(date('Y-m-28 00:00:00', $t)) + 7 * 24 * 3600;
			$first_day_of_month = date('Y-m-01 00:00:00', $t);
		}

		$withoutheadersoption = $input->getOption('withoutheaders');

		$month_export = date('Y-m', strtotime($first_day_of_month)-7200);


		include_spip('base/abstract_sql');
		$where = [
			"statut='ok'",
			"date_paiement LIKE '{$month_export}-%'",
			"parrain='don'"
		];
		if ($presta = $input->getOption('presta')) {
			$where[] = "presta LIKE ".sql_quote("$presta%");
		}

		$rows = sql_allfetsel("*", "spip_transactions", $where);

		$header = ["Date", "Libelle", "Debit", "Credit", "Code_compta", "No_Piece"];
		$ecritures = array();


		foreach($rows as $row) {

			$date = date('d/m/Y',strtotime($row['date_paiement']));
			$piece = "Transaction #" . $row['id_transaction'];
			$libelle = trim($row['contenu']);

			// Le compte à utiliser est le 754000 pour le café et le 754100 pour les autres
			$code_compta = "754100";
			if (preg_match(",\b(cafes?|cafés?|caf..?s?)\b,Uimsu", $libelle)) {
				$code_compta = "754000";
			}

			// et on complete le libelle
			if (strlen($row['auteur'])) {
				$nom = explode(' ', $row['auteur']);
				if (strpos(end($nom), '@') !== false and count($nom)>1) {
					array_pop($nom);
				}
				$nom = implode(' ', $nom);
				$libelle .= " $nom";

			}
			$libelle .= " $code_compta [$piece]";

			$debit = '';
			$credit = str_replace(".",",", sprintf("%.2f", $row['montant_regle']));

			$ecritures[] = [$date, $libelle, $debit, $credit, $code_compta, $piece];
		}

		$csv = "";
		if ($ecritures or !$withoutheadersoption) {
			$csv = $this->exporter_csv('', $ecritures, ';', $withoutheadersoption ? null : $header);

		}
		$output->writeln($csv);

		return Command::SUCCESS;
	}


	/**
	 * Exporte une ressource sous forme de fichier CSV
	 *
	 * La ressource peut etre un tableau ou une resource SQL issue d'une requete
	 * L'extension est choisie en fonction du delimiteur :
	 * - si on utilise ',' c'est un vrai csv avec extension csv
	 * - si on utilise ';' ou tabulation c'est pour E*cel, et on exporte en iso-truc, avec une extension .xls
	 *
	 * @uses exporter_csv_ligne()
	 *
	 * @param string $titre
	 *   titre utilise pour nommer le fichier
	 * @param array|resource $resource
	 * @param string $delim
	 *   delimiteur
	 * @param array $entetes
	 *   tableau d'en-tetes pour nommer les colonnes (genere la premiere ligne)
	 * @param bool $envoyer
	 *   pour envoyer le fichier exporte (permet le telechargement)
	 * @return string
	 */
	protected function exporter_csv($filename, $resource, $delim = ', ', $entetes = null) {

		include_spip('inc/exporter_csv');
		if ($delim == 'TAB') {
			$delim = "\t";
		}
		if (!in_array(trim($delim), array(',', ';', "\t"))) {
			$delim = ',';
		}

		if ($filename) {
			if ($delim == ',') {
				$extension = 'csv';
			} else {
				$extension = 'xls';
			}
			$filename = "$filename.$extension";
		}

		$output = "";
		if ($entetes and is_array($entetes) and count($entetes)) {
			$output .= exporter_csv_ligne($entetes, $delim);
		}
		while ($row = array_shift($resource)) {
			$output .= exporter_csv_ligne($row, $delim);
		}

		if ($filename) {
			file_put_contents($filename, $output);
			return $filename;
		}

		return $output;
	}

}