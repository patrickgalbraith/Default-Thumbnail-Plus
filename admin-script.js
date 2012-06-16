function dpt_update_descriptions() {
	jQuery('#dpt_filter-table tr').slice(1, jQuery('#dpt_filter-table tr').length-1).each(function(){
		
	});
}

function dpt_remove_row(curCell) {
	if(confirm("Are you sure you want to delete this item?")) {
		jQuery(curCell).closest('tr').remove();
	}
}
var rowid = 0;

jQuery(document).ready(function($) {
	
    rowid = parseInt($('#countimg').val()); 
	$('#dpt_add-filter-btn').click(function(){
		template_row = $('#template_row').clone();
		//rowid = $('#dpt_filter-table tbody tr').length - 1;
        rowid = rowid +1;
		
		template_row.attr('id', '');
		
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
        template_row.find('.dtp-item').remove();
		
		template_row.insertBefore('#template_row');
	});
	
	$('#dpt_submit-btn').click(function(){
		$('#template_row').remove();
        $('#countimg').val(rowid);
		return true;
	});
	
    $('#dpt_filter-table').on('click', 'input.slt-fs-button-more', function(){
        var item = $('#template_row').find('.dtp-item').clone();
        rowid = rowid +1;
        item.find('[id*=attachment_id_sample]').each(function(index, el){
            el_id = String($(el).attr('id'));
            $(el).attr('id', el_id.replace('attachment_id_sample', 'attachment_id_'+rowid));
        })
        item.find('[name*=attachment_id_sample]').each(function(index, el){
            el_id = String($(el).attr('name'));
            $(el).attr('name', el_id.replace('attachment_id_sample', 'attachment_id_'+rowid));
        })
        var categoryType = $(this).parent().parent().find('.filter_name').val();
        var categoryValue = $(this).parent().parent().find('.filter_value').val();
        console.log(categoryType);
        item.append('<input type="hidden" name="filter_name_'+rowid+'" value="'+categoryType+'"/>');
        item.append('<input type="hidden" name="filter_value_'+rowid+'" value="'+categoryValue+'"/>');
        $(this).parent().find('div.more-img-section').append(item);
    });
	
});