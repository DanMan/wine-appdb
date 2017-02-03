/*
 *   Misc JavaScipt for WineHQ Application Database
 */

// welcome
console.log('Welcome to the %cWineHQ%c AppDB',
            'color: #490708; font-size: 48pt; font-style: italic; font-weight: bold;',
            'color: #95493A; font-size: 48pt; font-style: italic; font-weight: bold;');

/*
 * jQuery old style toggle replacement
 * this useful object was removed in jquery 1.9
 */
$.fn.toggleClick = function(){
    var functions = arguments ;
    return this.click(function(){
            var iteration = $(this).data('iteration') || 0;
            functions[iteration].apply(this, arguments);
            iteration = (iteration + 1) % functions.length ;
            $(this).data('iteration', iteration);
    });
};

// load javascript file
// note: to force reload of js code, touch the dir
(function($){
    $.check_and_require=function(file,callback)
    {
        var full_path = sWebRoot + "js/" + file + '.js';
        var options = {
                       dataType:  'script',
                       cache:     true,
                       async:     false,
                       url:       full_path,
                       success:   callback
                      };
        $.ajax(options);
    }
})(jQuery);

// load css file once (use CDN if enabled)
var loadedCSS = [];
(function($){
    $.load_css_once=function(file)
    {
        if ($.inArray(file,loadedCSS) == -1) {
            $('<link rel="stylesheet" type="text/css" href="' + sWebRoot + 'css/' + file + '.css" />').appendTo("head");
            loadedCSS.push(file);
        }
    }
})(jQuery);

/*
 * Load our preferred wysiwyg editor on a field
   redactor II: http://imperavi.com/redactor/
 */
(function($){
    $.fn.whq_wysiwyg = function ()
    {
        // save this
        var t = this;

        // calculate height
        var iHeight = ($(t).attr("rows") ? ($(t).attr("rows") * 15) + 'px' : $(t).css('height'));

        // default redactor opts
        var redactor_opts = {
                minHeight: iHeight,
                maxHeight: iHeight,
                toolbarFixed: false,
                focus: false,
                overrideStyles: false,
                pastePlainText: true,
                dragImageUpload: false,
                clipboardImageUpload: false,
                multipleImageUpload: false,
                imageResizable: true,
                imagePosition: true,
                imageCaption: true,
                imageTag: 'figure',
                script: false,
                plugins: ['codemirror','alignment','table','fontcolor','fontfamily','fontsize','fullscreen','iconic'],
                buttons: ['fullscreen', 'html', 'format', 'bold', 'italic', 'underline', 'deleted',
                          'table', 'lists', 'alignment', 'horizontalrule','image', 'link'],
                buttonsHideOnMobile: ['image','table'],
                codemirror: {
                        lineNumbers: true,
                        lineWrapping: true,
                        mode: 'htmlmixed',
                        indentUnit: 4
                }
            };

        // load and execute codemirror
        $.check_and_require('codemirror', function()
        {
            // load css
            $.load_css_once('codemirror');
            $.load_css_once('redactor');

            // load and execute redactor
            $.check_and_require("redactor.min", function()
            {
                // load redactor on field
                $(t).redactor(redactor_opts);
            });
        });

        // return calling object
        return this;
    }
})(jQuery);

// execute on when document ready
$(document).ready(function()
{
    // rating hints
    (function()
    {
        var ratingdesc=[
                "",
                " Works flawlessly out of the box - no problems ",
                " Works flawlessly with DLL overrides, third party software or other settings ",
                " Works excellently for normal use;works fine in singleplayer but not multi ",
                " Works but has issues for normal use ",
                " Does not run or cannot be installed with Wine "
                ];
        var ratingstyle =[
                  "",
                  "platinum",
                  "gold",
                  "silver",
                  "bronze",
                  "garbage"
                  ];
        var changeRatingSelect = function()
        {
            var sel = $("#ratingSelect").prop('selectedIndex');
            $("#hint").attr('class', '');
            $("#hint").html(ratingdesc[sel]);
            $("#hint").addClass(ratingstyle[sel]);
        };
        $("#ratingSelect").change(function(){changeRatingSelect()});
        changeRatingSelect();
    })();

    // load nested forum comment
    $('.showComment').each(function()
    {
        $(this).click(function(e)
        {
            e.preventDefault();
            var id = $(this).data('id');
            $("#comment-"+id).html('<i class="fa fa-spinner fa-spin fa-fw"></a>');
            $.get('comment_body.php', {iCommentId: id}, function(comment)
            {
                $("#comment-"+id).html(comment);
            });
        });
    });

    // load table row by clicking anywhere on the row
    $("table").find("tr[data-donav]").each(function()
    {
        var sURL = $(this).data('donav');
        $(this).addClass('cursor-pointer');
        $(this).click(function(e)
        {
            document.location = sURL;
        });
    });

    // simple screenshot viewer
    // FIXME - this is a quickee hack of a viewer, could use some improving
    $(".whq-shot img").click(function(e)
    {
        e.preventDefault();
        e.stopPropagation();
        var sImg = '<div><img src="'+$(this).data("shot")+'" class="fill-width"></div>';
        $(sImg).dialog({
            'width': ((50 / 100) * $(window).width()),
            'height': ((70 / 100) * $(window).height())
        });
    });

    // wysiwyg HTML editor
    $("textarea.wysiwyg").each(function()
    {
        $(this).whq_wysiwyg();
    });

    // remove alert messages by clicking
    $("#whq-alert").click(function(){ $(this).fadeOut("slow"); });

    // debug log clicker
    $("div#dlogt").toggleClick(
    function()
    {
        $('div#dlogp').slideDown();
    },
    function()
    {
        $('div#dlogp').slideUp();
    });

    // HTML editor (redactor loader)


});

// done