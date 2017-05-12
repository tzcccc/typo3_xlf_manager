(function($){
    $(document).ready(function(){

        var $catKeySelector = $('#catKey');
        var $addKey = $('#addKey');
        var $addId = $('#addId');
        var cols = $('div.col');
        var fileName = $('div.file').attr('data-filename');
        var $content = $('.content');

        function initShowVariablesEvents(){
            var $showVariables = $('.show-variables:not(.initialized)');
            $showVariables.on('click',function(event) {
                if(!$showVariables.filter('.focused').length){
                    $(this).addClass('focused');
                }
            } );
            $showVariables.find('.to-close').on('click',function(){$showVariables.filter('.focused').removeClass('focused');return false;});
            $showVariables.find('input').each(function(i,e){
                var $input = $(e);
                var val = $input.val();
                var isNew = $input.closest('.row.id.new').length;
                if(!isNew){
                    val = val.replace(/\\'/g, "'");
                    val = val.replace(/\\/g, '#');
                    val = val.replace(/##/g, '\\');
                    $input.val(val);
                }
                $input.on('focus',function() {
                    $input.select();document.execCommand("copy");
                });
            });
            $showVariables.addClass('.initialized');
        }

        //sanitize id
        $addKey.on('keyup',function(){
            var val = $addKey.val();
            $addKey.val(val.replace(' ',''));
        });

        $addId.on('click',function(){
            var el = $(this);
            var idName = $addKey.val();
            var catKeyValue = $catKeySelector.val();
            if(catKeyValue){
                idName = catKeyValue+'.'+idName;
            }
            if(idName){
                cols.each(function(index,e){
                    var item = $(e);
                    var lastTd = item.find('span.row').last();
                    var lang = item.attr('data-lang');
                    var htmlToInsert = '';
                    if(index == 0){
                        var $new = $(window.variableMasterHTML);
                        $new.addClass('new');
                        $new.find('.keyid').val(idName);
                        $new.find('input[type="hidden"]').attr('name','xliff['+fileName+'__'+window.defaultLanguage+'__'+idName+']').val(idName);
                        $new.find('.show-variables .inner input').each(function(i,e){
                            var $input = $(e);
                            var val = $input.val();
                            $input.val(val.replace(window.variableKeyPath,window.variableKeyPath+idName));
                        });
                        //$id.find('textarea').val('<f:translate key="'+window.variableKeyPath+idName+'">\n{f:translate(key:\''+window.variableKeyPath+idName+'\')}\n\\TYPO3\\CMS\\Extbase\\Utility\\LocalizationUtility::translate(\''+window.variableKeyPath+idName+'\',\'\')');
                        if(lastTd.length){
                            lastTd.after($new);
                        } else {
                            item.append($new);
                        }
                        addDeleteClick($new.find('a.js-deletekey'));
                        initShowVariablesEvents();

                    } else if(lang) {
                        var $id = $('<span class="row new '+lang+'"></span>');
                        $id.append(window.inputMasterHTML);
                        console.log($id);
                        var $langInput = $id.find('input.lang');
                        $langInput.attr('name','xliff['+fileName+'__'+lang+'__'+idName+']').attr('tabindex',(lastTd.prevAll().length+2)+''+index);
                        if(window.defaultLanguage == lang){
                            $langInput.attr('required','required');
                        }
                        $id.find('span.after').text(idName);
                        var $img = $id.find('img').first();
                        var icon = lang.toUpperCase();
                        if(lang == 'en'){
                            icon = 'GB';
                        }
                        $img.attr('src',$img.attr('src')+icon+'.svg');

                        if(lastTd.length) {
                            lastTd.after($id);
                            if (index == 1) {
                                lastTd.next().find('input').first().focus();
                            }
                        } else {
                            item.append($id);
                            if (index == 1) {
                                item.find('input').first().focus();
                            }
                        }
                    }

                    $addKey.val('');;
                    //console.log(item,index);
                });
            } else {
                alert('Please specify the translate key ID.');
            }
            return false;
        });

        function addDeleteClick(item){
            item.on('click',function(){
                var index = item.closest('.row.id').prevAll().length;
                var cols = item.closest('div.file').find('div.col');
                if(confirm('Remove '+cols.first().find('span.row:eq('+index+')').find('input.keyid').val()+'?')){
                    cols.each(function(colIndex,colItem){
                        var toRemove = $(colItem).find('span.row:eq('+index+')');
                        toRemove.remove();
                        //colItem.down('span.td',index+1).remove();
                    });
                }
            });
            return false;
        }

        $('a.js-deletekey').each(function(i,e){
            addDeleteClick($(e));
        });

        $('#toggleSubcats').on('click',function(){
            var el = $(this);
            if(el.hasClass('active')){
                el.removeClass('active');
                $('.actions').removeClass('showSubcats');
                $('.language-file-wrap').removeClass('showSubcats');
            } else {
                el.addClass('active');
                $('.actions').addClass('showSubcats');
                $('.language-file-wrap').addClass('showSubcats');
            }
            return false;
        });

        $('.language-icon').each(function(i,e){
            var $icon = $(this);
            var $img = $icon.find('img');
            $icon.on('click',function(){
                var lang = $img.attr('data-lang');
                var firstColOffset = $('.col').first().width();
                var $langCol = $('.col[data-lang="'+lang+'"]');
                var langColWidth = $langCol.width();
                //get original scroll
                var originalScrollleft = $content.scrollLeft();
                //reset scrolleft
                $content.scrollLeft(0);
                //get language column offset from zero
                var offset = $langCol.offset().left;
                //set back original offset to initiate scroll from original position
                $content.scrollLeft(originalScrollleft);
                //animate language column
                $content.animate({scrollLeft:offset-(firstColOffset+langColWidth*0.1)});
                return false;
            });
        });


        //gather options for key prefix
        $('.cat-navi a').each(function(i,e){
            var $el = $(e);
            var val = $el.text();
            if (!val.match(/\.$/g)) {
                $catKeySelector.append('<option value="'+val+'">'+val+'</option>');
            }
        });


        initShowVariablesEvents();

    });
})(jQuery);