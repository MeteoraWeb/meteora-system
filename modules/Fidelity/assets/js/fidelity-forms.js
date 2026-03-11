(function(){
    document.addEventListener('DOMContentLoaded',function(){
        var forms=document.querySelectorAll('.ucg-fidelity-form[data-ajax="1"], .ucg-points-form[data-ajax="1"]');
        forms.forEach(function(form){
            form.addEventListener('submit',function(ev){
                ev.preventDefault();
                var fd=new FormData(form);
                fetch(window.location.href,{method:'POST',body:fd,credentials:'same-origin'})
                    .then(function(r){return r.text();})
                    .then(function(html){
                        var parser=new DOMParser();
                        var doc=parser.parseFromString(html,'text/html');
                        var msg=doc.getElementById('ucg-fid-message');
                        if(msg){alert(msg.innerText.trim());}
                    })
                    .catch(function(err){console.error('AJAX fidelity',err);});
            });
        });
    });
})();
