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
				'date',
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

		$month_export = date('Y-m', strtotime($first_day_of_month)-7200);


		include_spip('base/abstract_sql');

		$rows = sql_allfetsel("*", "spip_transactions","statut='ok' AND date_paiement LIKE '{$month_export}-%' AND parrain='don'");

		$header = ["Date", "Libelle", "Debit", "Credit", "Code_compta", "No_Piece"];
		$ecritures = array();


		foreach($rows as $row) {

			$date = date('d/m/Y',strtotime($row['date_paiement']));
			$piece = "";
			$libelle = trim($row['contenu']);

			// Le compte à utiliser est le 754000 pour le café et le 754100 pour les autres
			$code_compta = "754100";
			if (preg_match(",\b(cafes?|cafés?)\b,Uims", $libelle)) {
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
			$libelle .= " [Transaction #".$row['id_transaction']."]";

			$debit = '';
			$credit = str_replace(".",",", sprintf("%.2f", $row['montant_regle']));

			$ecritures[] = [$date, $libelle, $debit, $credit, $code_compta, $piece];
		}

		$exporter_csv = charger_fonction('exporter_csv', 'inc');
		$csv = $exporter_csv('', $ecritures, ';', $header);
		$output->writeln($csv);
	}
}
