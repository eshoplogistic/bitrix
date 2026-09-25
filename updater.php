<?php

if (IsModuleInstalled('eshoplogistic.delivery')) {
	\CModule::IncludeModule('eshoplogistic.delivery');

	if (is_dir(dirname(__FILE__).'/install/js')) {
		$updater->CopyFiles("install/js", "js/eshoplogistic.delivery");
	}
	if (is_dir(dirname(__FILE__).'/install/css')) {
		$updater->CopyFiles("install/css", "css/eshoplogistic.delivery");
	}
	if (is_dir(dirname(__FILE__).'/install/components')) {
		$updater->CopyFiles("install/components", "/bitrix/components");
	}
	if (is_dir(dirname(__FILE__).'/install/view')) {
		$updater->CopyFiles("install/view", "admin");
	}

	// One-time cleanup of files shipped in very old releases and never removed since
	// (incremental updates only add/overwrite — they never delete files missing from the
	// package). Both were flagged by the marketplace security scan:
	// - install/components/button/ (this module's own copy, under bitrix/modules/...) —
	//   legacy unnamespaced "quick order button" component; its ajax.php has no CSRF/auth
	//   checks and is directly web-reachable if hit before/without running the installer.
	// - bitrix/components/button/ — the runtime copy of the same legacy component, installed
	//   via CopyDirFiles in earlier versions' InstallFiles(). Current InstallFiles() already
	//   removes this on every fresh install/reinstall, but sites that only ever apply
	//   incremental updates (like this one) never re-run InstallFiles().
	// - lib/options.php — dead duplicate of the root options.php page, accidentally shipped
	//   in version 2.4.5 and never cleaned up.
	DeleteDirFilesEx('/bitrix/modules/eshoplogistic.delivery/install/components/button');
	DeleteDirFilesEx('/bitrix/components/button');
	@unlink($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/eshoplogistic.delivery/lib/options.php');

	// Строка "Выгрузка в ТК" в верхнем блоке заказа (Unloading::orderInfoBlockShow) —
	// InstallEvents() на обновлениях не вызывается, регистрируем здесь (INSERT IGNORE,
	// повторный запуск безопасен).
	\Bitrix\Main\EventManager::getInstance()->registerEventHandler(
		'sale',
		'onSaleAdminOrderInfoBlockShow',
		'eshoplogistic.delivery',
		'Eshoplogistic\Delivery\Event\Unloading',
		'orderInfoBlockShow'
	);
}
