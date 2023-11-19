# IDE companion for TYPO3

LSP implementations for TYPO3 (Fluid and more)

## What this want's to be
* LSP implementation for a specific TYPO3 installation which is done by installing this as an extension to the TYPO3 installation ;-)
* LSP implementation for TypoScript as far as the TYPO3 built in TypoScript parser allows it
* Maybe resolving of EXT:xxx paths in all kinds of files (via LSP or an IDEs client plugin still has to be resesearched)


## What this will not be

* Standalone LSP for Fluid or TypoScript
* Linter or formatter for Fluid or TypoScript


## Installation (wip)

Needs to be installed as TYPO3 extension either by using composer mode installation (not tested at the moment) or via executing `composer install` in the Resources/Private sub directory

Needs an LSP client, for neovim use something like

```lua
vim.api.nvim_create_autocmd({ "FileType" }, {
	pattern = { "html" },
	callback = function()
		vim.lsp.start({
			name = "typo3",
			cmd = { "/usr/bin/php", "/var/www/PATH/typo3/sysext/core/bin/typo3", "idecompanion:lsp" },
			root_dir = "/var/www/PATH",
		})
	end,
	group = vim.api.nvim_create_augroup("typo3", { clear = true }),
})
```

For vscode you can use [torokati44.glspc](torokati44/vscode-glspc) as generic LSP client.

This will hopefully be simplified in the future ;-)
