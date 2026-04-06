{**
 * plugins/importexport/csv/templates/settingsForm.tpl
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * CSV Import settings tab for Settings > Website
 *}
<tab id="csvImportPlugin" label="{translate key="plugins.importexport.csv.displayName"}">
	<pkp-form
		v-bind="components.{$smarty.const.FORM_CSV_IMPORT}"
		@set="set"
	/>
</tab>
