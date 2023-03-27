<?php
/* Copyright (C) 2005       Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2010-2020  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2010-2012  Juanjo Menent           <jmenent@2byte.es>
 * Copyright (C) 2018       Nicolas ZABOURI         <info@inovea-conseil.com>
 * Copyright (C) 2018       Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2019       Markus Welters          <markus@welters.de>
 * Copyright (C) 2023       Alexandre Spangaro      <aspangaro@open-dsi.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/compta/prelevement/create.php
 *  \ingroup    prelevement
 *	\brief      Page to create a direct debit order or a credit transfer order
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/prelevement/class/bonprelevement.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/prelevement.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

// Load translation files required by the page
$langs->loadLangs(array('banks',  'bills', 'categories', 'companies', 'withdrawals'));

$type = GETPOST('type', 'aZ09');

// Get supervariables
$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$mode = GETPOST('mode', 'alpha') ?GETPOST('mode', 'alpha') : 'real';
$format = GETPOST('format', 'aZ09');
$id_bankaccount = GETPOST('id_bankaccount', 'int');
$executiondate = dol_mktime(0, 0, 0, GETPOST('remonth', 'int'), GETPOST('reday', 'int'), GETPOST('reyear', 'int'));

$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;

$hookmanager->initHooks(array('directdebitcreatecard', 'globalcard'));

// Security check
if ($user->socid) {
	$socid = $user->socid;
}
if ($type == 'bank-transfer') {
	$result = restrictedArea($user, 'paymentbybanktransfer', '', '', '');
} else {
	$result = restrictedArea($user, 'prelevement', '', '', 'bons');
}

$error = 0;

/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$massaction = '';
}

$parameters = array('mode' => $mode, 'format' => $format, 'limit' => $limit, 'page' => $page, 'offset' => $offset);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Change customer bank information to withdraw
	if ($action == 'modify') {
		for ($i = 1; $i < 9; $i++) {
			dolibarr_set_const($db, GETPOST("nom$i"), GETPOST("value$i"), 'chaine', 0, '', $conf->entity);
		}
	}
	if ($action == 'create') {
		$default_account=($type == 'bank-transfer' ? 'PAYMENTBYBANKTRANSFER_ID_BANKACCOUNT' : 'PRELEVEMENT_ID_BANKACCOUNT');

		if ($id_bankaccount != $conf->global->{$default_account}) {
			$res = dolibarr_set_const($db, $default_account, $id_bankaccount, 'chaine', 0, '', $conf->entity);	//Set as default
		}

		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
		$bank = new Account($db);
		$bank->fetch($conf->global->{$default_account});
		// ICS is not mandatory with payment by bank transfer
		/*if ((empty($bank->ics) && $type !== 'bank-transfer')
			|| (empty($bank->ics_transfer) && $type === 'bank-transfer')
		) {*/
		if (empty($bank->ics) && $type !== 'bank-transfer') {
			$errormessage = str_replace('{url}', $bank->getNomUrl(1, '', '', -1, 1), $langs->trans("ErrorICSmissing", '{url}'));
			setEventMessages($errormessage, null, 'errors');
			$action = '';
			$error++;
		}


		$delayindays = 0;
		if ($type != 'bank-transfer') {
			$delayindays = $conf->global->PRELEVEMENT_ADDDAYS;
		} else {
			$delayindays = $conf->global->PAYMENTBYBANKTRANSFER_ADDDAYS;
		}
		$bprev = new BonPrelevement($db);
		$executiondate = dol_mktime(0, 0, 0, GETPOST('remonth', 'int'), (GETPOST('reday', 'int') + $delayindays), GETPOST('reyear', 'int'));

		// $conf->global->PRELEVEMENT_CODE_BANQUE and $conf->global->PRELEVEMENT_CODE_GUICHET should be empty (we don't use them anymore)
		$result = $bprev->create($conf->global->PRELEVEMENT_CODE_BANQUE, $conf->global->PRELEVEMENT_CODE_GUICHET, $mode, $format, $executiondate, 0, $type);
		if ($result < 0) {
			setEventMessages($bprev->error, $bprev->errors, 'errors');
		} elseif ($result == 0) {
			$mesg = $langs->trans("NoInvoiceCouldBeWithdrawed", $format);
			setEventMessages($mesg, null, 'errors');
			$mesg .= '<br>'."\n";
			foreach ($bprev->invoice_in_error as $key => $val) {
				$mesg .= '<span class="warning">'.$val."</span><br>\n";
			}
		} else {
			if ($type != 'bank-transfer') {
				$texttoshow = $langs->trans("DirectDebitOrderCreated", '{s}');
				$texttoshow = str_replace('{s}', $bprev->getNomUrl(1), $texttoshow);
				setEventMessages($texttoshow, null);
			} else {
				$texttoshow = $langs->trans("CreditTransferOrderCreated", '{s}');
				$texttoshow = str_replace('{s}', $bprev->getNomUrl(1), $texttoshow);
				setEventMessages($texttoshow, null);
			}

			header("Location: ".DOL_URL_ROOT.'/compta/prelevement/card.php?id='.urlencode($bprev->id).'&type='.urlencode($type));
			exit;
		}
	}
	$objectclass = "BonPrelevement";
	if ($type == 'bank-transfer') {
		$uploaddir = $conf->paymentbybanktransfer->dir_output;
	} else {
		$uploaddir = $conf->prelevement->dir_output;
	}
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}


/*
 * View
 */

$form = new Form($db);

$thirdpartystatic = new Societe($db);
if ($type != 'bank-transfer') {
	$invoicestatic = new Facture($db);
} else {
	$invoicestatic = new FactureFournisseur($db);
}
$bprev = new BonPrelevement($db);
$arrayofselected = is_array($toselect) ? $toselect : array();
// List of mass actions available
$arrayofmassactions = array(
);
if (GETPOST('nomassaction', 'int') || in_array($massaction, array('presend', 'predelete'))) {
	$arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

llxHeader('', $langs->trans("NewStandingOrder"));

if (prelevement_check_config($type) < 0) {
	$langs->load("errors");
	$modulenametoshow = "Withdraw";
	if ($type == 'bank-transfer') {
		$modulenametoshow = "PaymentByBankTransfer";
	}
	setEventMessages($langs->trans("ErrorModuleSetupNotComplete", $langs->transnoentitiesnoconv($modulenametoshow)), null, 'errors');
}


/*$h=0;
$head[$h][0] = DOL_URL_ROOT.'/compta/prelevement/create.php';
$head[$h][1] = $langs->trans("NewStandingOrder");
$head[$h][2] = 'payment';
$hselected = 'payment';
$h++;

print dol_get_fiche_head($head, $hselected, $langs->trans("StandingOrders"), 0, 'payment');
*/

$title = $langs->trans("NewStandingOrder");
if ($type == 'bank-transfer') {
	$title = $langs->trans("NewPaymentByBankTransfer");
}

print load_fiche_titre($title);

if (empty($conf->global->WITHDRAWAL_NOT_SHOW_BANK_ACCOUNT)) {
	print_barre_liste($langs->trans('ListOfAccounts'), $page, $_SERVER["PHP_SELF"], '', '', '', '', '', '', 'bank');

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("BankAccounts").'</td>';
	print '<td>'.$langs->trans("Label").'</td>';
	print '<td>'.$langs->trans("BankAccountNumber").'</td>';
	print '<td class="center">'.$langs->trans("Balance").'</td>';
	print '</tr>';

	// Load array of financial accounts (opened only & type current)
	$accounts = array();

	$sql = "SELECT b.rowid, b.label, b.courant, b.rappro, b.number, b.fk_accountancy_journal, b.currency_code, b.datec as date_creation, b.tms as date_update";
	$sql .= " FROM ".MAIN_DB_PREFIX."bank_account as b";
	$sql .= " WHERE b.entity IN (".getEntity('bank_account').")";
	$sql .= " AND courant = 1";
	$sql .= " AND clos = 0";

	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;
		while ($i < $num) {
			$objp = $db->fetch_object($resql);
			$accountstatic = new Account($db);
			$accountstatic->fetch($objp->rowid);

			print '<tr class="oddeven">';

			print '<td class="nowrap">'.$accountstatic->getNomUrl(1).'</td>';

			print '<td class="nowrap">'.$accountstatic->label.'</td>';

			print '<td class="nowrap">'.$accountstatic->number.'</td>';

			$solde = $accountstatic->solde(1);
			print '<td class="amount right">'.price($solde, 0, $langs, 1, -1, -1, $accountstatic->currency_code).'</td>';

			$i++;
		}
		$db->free($resql);
	} else {
		print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}

	print "</table>";
	print "<br>\n";
}

/*
 * Invoices waiting for withdraw
 */

$sql = "SELECT f.ref, f.rowid, f.date_lim_reglement as dlp, f.total_ttc, s.nom as name, s.rowid as socid,";
$sql .= " pfd.rowid as request_row_id, pfd.date_demande, pfd.amount, pfd.fk_soc_rib";
if ($type == 'bank-transfer') {
	$sql .= ", ref_supplier";
	$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn as f,";
} else {
	$sql .= " FROM ".MAIN_DB_PREFIX."facture as f,";
}
$sql .= " ".MAIN_DB_PREFIX."societe as s,";
$sql .= " ".MAIN_DB_PREFIX."prelevement_facture_demande as pfd";
$sql .= " WHERE s.rowid = f.fk_soc";
$sql .= " AND f.entity IN (".getEntity('invoice').")";
if (empty($conf->global->WITHDRAWAL_ALLOW_ANY_INVOICE_STATUS)) {
	$sql .= " AND f.fk_statut = ".Facture::STATUS_VALIDATED;
}
//$sql .= " AND pfd.amount > 0";
$sql .= " AND f.total_ttc > 0"; // Avoid credit notes
$sql .= " AND pfd.traite = 0";
$sql .= " AND pfd.ext_payment_id IS NULL";
if ($type == 'bank-transfer') {
	$sql .= " AND pfd.fk_facture_fourn = f.rowid";
} else {
	$sql .= " AND pfd.fk_facture = f.rowid";
}
if ($socid > 0) {
	$sql .= " AND f.fk_soc = ".((int) $socid);
}

$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
	$result = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($result);
	if (($page * $limit) > $nbtotalofrecords) {
		// if total resultset is smaller then paging size (filtering), goto and load page 0
		$page = 0;
		$offset = 0;
	}
}

$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$i = 0;

	$param = '';
	if ($limit > 0 && $limit != $conf->liste_limit) {
		$param .= '&limit='.urlencode($limit);
	}
	if ($socid) {
		$param .= '&socid='.urlencode($socid);
	}
	if ($option) {
		$param .= "&option=".urlencode($option);
	}

	print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="page" value="'.$page.'">';
	if (!empty($limit)) {
		print '<input type="hidden" name="limit" value="'.$limit.'"/>';
	}
	if ($type != '') {
		print '<input type="hidden" name="type" value="'.$type.'">';
	}

	$title = $langs->trans("InvoiceWaitingWithdraw");
	if ($type == 'bank-transfer') {
		$title = $langs->trans("InvoiceWaitingPaymentByBankTransfer");
	}
	print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, '', '', $massactionbutton, $num, $nbtotalofrecords, 'bill', 0, '', '', $limit);

	$tradinvoice = "Invoice";
	if ($type == 'bank-transfer') {
		$tradinvoice = "SupplierInvoice";
	}

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans($tradinvoice).'</td>';
	print '<td>'.$langs->trans("ThirdParty").'</td>';
	if ($type == 'bank-transfer') {
		print '<td>' . $langs->trans("RefSupplier") . '</td>';
	}
	print '<td class="center">'.$langs->trans("DateDue").'</td>';
	print '<td>'.$langs->trans("RIB").'</td>';
	print '<td>'.$langs->trans("RUM").'</td>';
	print '<td class="right">'.$langs->trans("AmountTTC").'</td>';
	print '<td class="right">'.$langs->trans("DateRequest").'</td>';
	if ($massactionbutton || $massaction) { // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
		print '<td class="center">'.$form->showCheckAddButtons('checkforselect', 1).'</td>';
	}
	print '</tr>';

	if ($num) {
		require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
		$bac = new CompanyBankAccount($db);

		while ($i < $num && $i < $limit) {
			$obj = $db->fetch_object($resql);

			if (!empty($obj->fk_soc_rib))	$bac->fetch($obj->fk_soc_rib);
			else							$bac->fetch(0, $obj->socid);

			print '<tr class="oddeven">';

			// Ref invoice
			print '<td>';
			$invoicestatic->id = $obj->rowid;
			$invoicestatic->ref = $obj->ref;
			print $invoicestatic->getNomUrl(1, 'withdraw');
			print '</td>';

			// Thirdparty
			print '<td>';
			$thirdpartystatic->fetch($obj->socid);
			print $thirdpartystatic->getNomUrl(1, 'ban');
			print '</td>';

			// Ref supplier
			if ($type == 'bank-transfer') {
				print '<td>';
				print $obj->ref_supplier;
				print '</td>';
			}

			// Date limit payment
			print '<td class="center">';
			print dol_print_date($db->jdate($obj->dlp), 'day');
			print '</td>';

			// RIB
			print '<td>';
			print $bac->iban.(($bac->iban && $bac->bic) ? ' / ' : '').$bac->bic;
			if ($bac->verif() <= 0) {
				print img_warning('Error on default bank number for IBAN : '.$bac->error_message);
			}
			print '</td>';

			// RUM
			print '<td>';
			$rumtoshow = $thirdpartystatic->display_rib('rum');
			if ($rumtoshow) {
				print $rumtoshow;
				$format = $thirdpartystatic->display_rib('format');
				if ($type != 'bank-transfer') {
					if ($format) {
						print ' ('.$format.')';
					}
				}
			} else {
				print img_warning($langs->trans("NoBankAccountDefined"));
			}
			print '</td>';
			// Amount
			print '<td class="right amount">';
			print price($obj->amount, 0, $langs, 0, 0, -1, $conf->currency);
			print '</td>';
			// Date
			print '<td class="right">';
			print dol_print_date($db->jdate($obj->date_demande), 'day');
			print '</td>';
			// Action column
			if ($massactionbutton || $massaction) { // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
				print '<td class="nowrap center">';
				$selected = 0;
				if (in_array($obj->request_row_id, $arrayofselected)) {
					$selected = 1;
				}
				print '<input id="cb'.$obj->request_row_id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->request_row_id.'"'.($selected ? ' checked="checked"' : '').'>';
				print '</td>';
			}
			print '</tr>';
			$i++;
		}
	} else {
		print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}
	print "</table>";
	print "</form>";
	print "<br>\n";
} else {
	dol_print_error($db);
}

$nb = $bprev->nbOfInvoiceToPay($type);
$pricetowithdraw = $bprev->SommeAPrelever($type);
if ($nb < 0) {
	dol_print_error($bprev->error);
}

if ($mesg) {
	print $mesg;
}

print '<div class="tabsAction">'."\n";

print '<form action="'.$_SERVER['PHP_SELF'].'?action=create" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="type" value="'.$type.'">';
if ($nb) {
	if ($pricetowithdraw) {
		$title = $langs->trans('BankToReceiveWithdraw').': ';
		if ($type == 'bank-transfer') {
			$title = $langs->trans('BankToPayCreditTransfer').': ';
		}
		print $title;
		print img_picto('', 'bank_account');
		$default_account = ($type == 'bank-transfer' ? 'PAYMENTBYBANKTRANSFER_ID_BANKACCOUNT' : 'PRELEVEMENT_ID_BANKACCOUNT');
		print $form->select_comptes($conf->global->$default_account, 'id_bankaccount', 0, "courant=1", 0, '', 0, '', 1);
		print ' - ';

		print $langs->trans('ExecutionDate').' ';
		$datere = dol_mktime(0, 0, 0, GETPOST('remonth', 'int'), GETPOST('reday', 'int'), GETPOST('reyear', 'int'));
		print $form->selectDate($datere, 're');


		if ($mysoc->isInEEC()) {
			$title = $langs->trans("CreateForSepa");
			if ($type == 'bank-transfer') {
				$title = $langs->trans("CreateSepaFileForPaymentByBankTransfer");
			}

			if ($type != 'bank-transfer') {
				print '<select name="format">';
				print '<option value="FRST"'.(GETPOST('format', 'aZ09') == 'FRST' ? ' selected="selected"' : '').'>'.$langs->trans('SEPAFRST').'</option>';
				print '<option value="RCUR"'.(GETPOST('format', 'aZ09') == 'RCUR' ? ' selected="selected"' : '').'>'.$langs->trans('SEPARCUR').'</option>';
				print '</select>';
			}
			print '<input class="butAction" type="submit" value="'.$title.'"/>';
		} else {
			$title = $langs->trans("CreateAll");
			if ($type == 'bank-transfer') {
				$title = $langs->trans("CreateFileForPaymentByBankTransfer");
			}
			print '<a class="butAction" type="submit" href="create.php?action=create&format=ALL&type='.$type.'">'.$title."</a>\n";
		}
	} else {
		if ($mysoc->isInEEC()) {
			$title = $langs->trans("CreateForSepaFRST");
			if ($type == 'bank-transfer') {
				$title = $langs->trans("CreateSepaFileForPaymentByBankTransfer");
			}
			print '<a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans("AmountMustBePositive").'">'.$title."</a>\n";

			if ($type != 'bank-transfer') {
				$title = $langs->trans("CreateForSepaRCUR");
				print '<a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans("AmountMustBePositive").'">'.$title."</a>\n";
			}
		} else {
			$title = $langs->trans("CreateAll");
			if ($type == 'bank-transfer') {
				$title = $langs->trans("CreateFileForPaymentByBankTransfer");
			}
			print '<a class="butActionRefused classfortooltip" href="#">'.$title."</a>\n";
		}
	}
} else {
	$titlefortab = $langs->transnoentitiesnoconv("StandingOrders");
	$title = $langs->trans("CreateAll");
	if ($type == 'bank-transfer') {
		$titlefortab = $langs->transnoentitiesnoconv("PaymentByBankTransfers");
		$title = $langs->trans("CreateFileForPaymentByBankTransfer");
	}
	print '<a class="butActionRefused classfortooltip" href="#" title="'.dol_escape_htmltag($langs->transnoentitiesnoconv("NoInvoiceToWithdraw", $titlefortab, $titlefortab)).'">'.$title."</a>\n";
}

print "</form>\n";

print "</div>\n";
print '</form>';


/*
 * List of latest withdraws
 */
/*
$limit=5;

print load_fiche_titre($langs->trans("LastWithdrawalReceipts",$limit),'','');

$sql = "SELECT p.rowid, p.ref, p.amount, p.statut";
$sql.= ", p.datec";
$sql.= " FROM ".MAIN_DB_PREFIX."prelevement_bons as p";
$sql.= " WHERE p.entity IN (".getEntity('invoice').")";
$sql.= " ORDER BY datec DESC";
$sql.=$db->plimit($limit);

$result = $db->query($sql);
if ($result)
{
	$num = $db->num_rows($result);
	$i = 0;

	print"\n<!-- debut table -->\n";
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("Ref").'</td>';
	print '<td class="center">'.$langs->trans("Date").'</td><td class="right">'.$langs->trans("Amount").'</td>';
	print '</tr>';

	while ($i < min($num,$limit))
	{
		$obj = $db->fetch_object($result);


		print '<tr class="oddeven">';

		print "<td>";
		$bprev->id=$obj->rowid;
		$bprev->ref=$obj->ref;
		print $bprev->getNomUrl(1);
		print "</td>\n";

		print '<td class="center">'.dol_print_date($db->jdate($obj->datec),'day')."</td>\n";

		print '<td class="right"><span class="amount">'.price($obj->amount,0,$langs,0,0,-1,$conf->currency)."</span></td>\n";

		print "</tr>\n";
		$i++;
	}
	print "</table><br>";
	$db->free($result);
}
else
{
	dol_print_error($db);
}
*/

// End of page
llxFooter();
$db->close();
