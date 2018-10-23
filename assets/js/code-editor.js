 'use strict';
 (function($){
	$(function(){
		var i = 0;
		$('.code-editor').each( function(){
			i++;

			var cmSettings = {
				indentUnit: 2,
				tabSize: 2
			}

			if( $(this).attr('data-mode') ){
				cmSettings['mode'] = $(this).attr('data-mode');
			}

			var editorSettings = wp.codeEditor.defaultSettings ? _.clone( wp.codeEditor.defaultSettings ) : {};
			editorSettings.codemirror = _.extend(
				{},
				editorSettings.codemirror,
				cmSettings
			);
			window['editor_'+i] = wp.codeEditor.initialize( $(this), editorSettings );
		});
	});
 })(jQuery);