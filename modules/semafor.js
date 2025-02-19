export class semafor {
    constructor(params={}) {
        var me = this;
        this.service = params.service ? params.service : "semafor.php";
        this.omk = params.omk ? params.omk : false;
        this.init = function () {
            console.log(me);
        }

        this.search = async function (type,search,nb=10){
            let url = me.service+"?type="+type+"&nb="+nb+"&search="+search;
            let reponse = await fetch(url, {                
                method: 'GET'
            }).catch(error => {
                console.error('Error fetching the data:', error);
                return false;
            }); 
            if(!reponse.ok){
                console.log(reponse);
                return false;
            }else{
                return await reponse.json();
            }  
        }

        this.getOmkComp = async function(s){
            if(!me.omk)return false;

            let dt = {};
            dt['o:resource_class']="rome:competence";
            dt['o:resource_template']='ROME Référence';            
            dt["rome:codeOgr"]=s.data.code_ogr;
            dt["rome:libelle"]=s.data.titre;
            let data = {'rt':'ROME Référence','c':'rome:competence',
                'dt':dt,	                            
                'verif':{'rome:codeOgr':s.data.code_ogr},
                'index':'rome:competence'+s.data.code_ogr};
            return await me.omk.getsetResource(data);
        }    

        this.setFind = async function(item,find,cb){

            let arrPromise = [],dt={'rome:hasCompetence':[]};

            find.search_results.forEach(r=>{
                arrPromise.push(me.getOmkComp(r));
            })
            Promise.all(arrPromise).then(values => {
                values.forEach((oComp,i)=>{
                    let anno = {'rome:similarity':[],'rome:codeOgr':[]}, 
                        comp = {};
                    //création de l'annotation
                    anno['rome:similarity'].push({
                        "@value": find.search_results[i].similarity+"",
                        "type": "literal",
                        "property_id": me.omk.getPropId('rome:similarity'),
                    });
                    anno['rome:codeOgr'].push({
                        "@value": find.search_results[i].data.code_ogr+"",
                        "type": "literal",
                        "property_id": me.omk.getPropId('rome:codeOgr'),
                    });
                    comp.v={'rid':oComp['o:id']};
                    comp.a=anno;
                    dt['rome:hasCompetence'].push(comp);
                })
                me.omk.updateRessource(item["o:id"], dt, 'items', null, 'PATCH',cb,item);
            })
        } 

        function complete(data){
            console.log(data);
        }    
        this.init();
    }
}
