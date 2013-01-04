var ci_post_importer;

jQuery(document).ready(function($){
	
	ci_post_importer = new CityIndexPostEditor($);
});

/**
 * @class The main javascript class for cityindex post importer
 */
var CityIndexPostEditor = function($){
	
	console.log($);
	this.init();
	
	this.init = function(){
		console.log('init');
	}
}