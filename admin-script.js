function dpt_update_descriptions() {
	jQuery('#dpt_filter-table tr').slice(1, jQuery('#dpt_filter-table tr').length-1).each(function(){
		
	});
}

function dpt_remove_row(curCell) {
	if(confirm("Are you sure you want to delete this item?")) {
		jQuery(curCell).closest('tr').remove();
	}
}

jQuery(document).ready(function($) {
	
	$('#dpt_add-filter-btn').click(function(){
		template_row = $('#template_row').clone();
		//rowid = $('#dpt_filter-table tbody tr').length - 1;
		
		rowid = parseInt($('#dpt_filter-table tbody tr').last().prev().attr('data-array_index')) + 1;
		
		template_row.attr('id', '');
		
		template_row.attr('data-array_index', rowid);
		
		template_row.find('.filter_name').attr('name', 'filter_name_'+rowid);
		template_row.find('.filter_value').attr('name', 'filter_value_'+rowid);
		template_row.find('[id*="attachment_id_template"]').each(function(index, el) {
			el_id = String($(el).attr('id'));
			$(el).attr('id', el_id.replace('attachment_id_template', 'attachment_id_'+rowid));
		});
		template_row.find('[name*="attachment_id_template"]').each(function(index, el) {
			el_id = String($(el).attr('name'));
			$(el).attr('name', el_id.replace('attachment_id_template', 'attachment_id_'+rowid));
		});
		
		template_row.insertBefore('#template_row');
	});
	
	$('#dpt_submit-btn').click(function(){
		$('#template_row').remove();
		return true;
	});
	
});