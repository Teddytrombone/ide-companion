# ide-companion

LSP for TYPO3 Fluid and more

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
