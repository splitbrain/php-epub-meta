
var bookapi = {
    $dialog: null,

    resulttpl:
        '<div class="result">' +
        '    <img src="" />'+
        '    <div>' +
        '    <div class="buttons">' +
        '    <button class="btn-repl">replace</button><br />' +
        '    <button class="btn-fill">fill in</button>' +
        '    </div>' +
        '    <h1 class="title"></h1>' +
        '    <p class="authors"></p>' +
        '    <p class="description"></p>' +
        '    <p class="more">' +
        '        <span class="lang"></span>' +
        '        <span class="publisher"></span>' +
        '        <span class="subjects"></span>' +
        '    </p>' +
        '    </div>' +
        '</div>',

    init: function(){
        $('body').append('<div id="bookapi"></div>');
        bookapi.$dialog = $('#bookapi');
        bookapi.$dialog.dialog(
            {
                autoOpen: false,
                title: 'Lookup Book Data',
                width: 800,
                height: 500
            }
        );
        bookapi.$dialog.append('<div class="head">Lookup: <input type="text" id="bookapi-q" /></div>')
                       .append('<div id="bookapi-out"></div>');
        bookapi.$out = $('#bookapi-out');

        $('#bookpanel').append('<div id="bookapi-s">Search</div>');
        $('#bookapi-s').click(bookapi.open);

        $('#bookapi-q').keypress(
            function(event){
                if(event.which == 13){
                    event.preventDefault();
                    bookapi.search();
                }
            });

    },

    open: function(){
        bookapi.$dialog.dialog('open');

        var query = $('#bookpanel input[name=title]').val();
        $('#bookapi-q').val(query);

        bookapi.search();
    },

    search: function(){
        bookapi.$out.html('please wait...');
        $.ajax({
            type: 'GET',
            data: {'api':$('#bookapi-q').val()},
            success: bookapi.searchdone,
            dataType: 'json'
        });
    },

    searchdone: function(data){
        if(data.totalItems == 0){
            bookapi.$out.html('Found no results.<br />Try adjusting the query and retry.');
            return;
        }

        bookapi.$out.html('');
        for(i=0; i<data.items.length; i++){
            $res = $(bookapi.resulttpl);
            if(data.items[i].volumeInfo.title)
                $res.find('.title').html(data.items[i].volumeInfo.title);
            if(data.items[i].volumeInfo.authors)
                $res.find('.authors').html(data.items[i].volumeInfo.authors.join(', '));
            if(data.items[i].volumeInfo.description)
                $res.find('.description').html(data.items[i].volumeInfo.description);
            if(data.items[i].volumeInfo.language)
                $res.find('.lang').html('['+data.items[i].volumeInfo.language+']');
            if(data.items[i].volumeInfo.publisher)
                $res.find('.publisher').html(data.items[i].volumeInfo.publisher);
            if(data.items[i].volumeInfo.categories)
                $res.find('.subjects').html(data.items[i].volumeInfo.categories.join(', '));
            if(data.items[i].volumeInfo.imageLinks)
                if(data.items[i].volumeInfo.imageLinks.thumbnail)
                    $res.find('img').attr('src',data.items[i].volumeInfo.imageLinks.thumbnail);

            $res.find('.btn-repl').click(data.items[i].volumeInfo,bookapi.replace);
            $res.find('.btn-fill').click(data.items[i].volumeInfo,bookapi.fillin);

            bookapi.$out.append($res);
        }
    },

    replace: function(event){
        item = event.data;

        if(item.title)
            $('#bookpanel input[name=title]').val(item.title);
        if(item.description)
            $('#bookpanel textarea[name=description]').val(item.description);
            $wysiwyg[0].updateFrame();
        if(item.language)
            $('#bookpanel input[name=language]').val(item.language);
        if(item.publisher)
            $('#bookpanel input[name=publisher]').val(item.publisher);
        if(item.categories)
            $('#bookpanel input[name=subjects]').val(item.categories.join(', '));
    },

    fillin: function(event){
        item = event.data;

        if(item.title && $('#bookpanel input[name=title]').val() == '')
            $('#bookpanel input[name=title]').val(item.title);
        if(item.description && $('#bookpanel textarea[name=description]').val() == '')
            $('#bookpanel textarea[name=description]').val(item.description);
            $wysiwyg[0].updateFrame();
        if(item.language && $('#bookpanel input[name=language]').val() == '')
            $('#bookpanel input[name=language]').val(item.language);
        if(item.publisher && $('#bookpanel input[name=publisher]').val() == '')
            $('#bookpanel input[name=publisher]').val(item.publisher);
        if(item.categories && $('#bookpanel input[name=subjects]').val() == '')
            $('#bookpanel input[name=subjects]').val(item.categories.join(', '));
    }

}


$(function(){
    bookapi.init();
});
