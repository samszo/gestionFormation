export class francetravail {
    constructor(params={}) {
        var me = this;
        this.service = params.service ? params.service : "francetravail.php";
        this.api = params.api ? params.service : "https://api.francetravail.io/partenaire/";
        this.omk = params.omk ? params.omk : false;
        this.token = false;
        this.init = function () {
            console.log(me);
            me.token = getToken();
        }

        this.search = async function(type,search,nb=10){

            let url = me.api, delais = 1000;
            switch (type) {
                case 'offres':
                    url += "offresdemploi/v2/offres/search?"+search;
                    delais = 200;                    
                    break;
                case 'competence':
                    url += "rome-competences/v1/competences/competence/"+search;
                    break;            
                case 'appellation':
                    url += "rome-metiers/v1/metiers/appellation/requete?q="+search;
                    break;            
                default:
                    break;
            }
            await getToken();
            await new Promise(function(resolve, reject) {
                setTimeout(resolve, delais, 'Timeout Done');
            })
            return await query(url);            
        }

        this.get = async function(type,search,nb=10){

            let url = me.api,
                // 1 Second delay
                timeOutPromise = new Promise(function(resolve, reject) {
                    setTimeout(resolve, 1000, 'Timeout Done');
                });
            switch (type) {
                case 'competence':
                    url += "rome-competences/v1/competences/competence/"+search;
                    break;            
                case 'appellation':
                    url += "rome-metiers/v1/metiers/appellation/"+search;
                    break;            
                default:
                    break;
            }
            await getToken();
            await timeOutPromise
            return await query(url);            
        }

        // récupère un token francetravail valid
        async function getToken() {
            let response = await fetch(me.service);
            me.token = await response.text();
            return me.token; 
        }

        // Fonction pour la requête sur France Travail
        async function query(url) {
            let reponse = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer '+me.token
                }
            })
            .catch(error => {
                console.error('Error fetching the data:', error);
                return false;
            });

            if(!reponse.ok){
                console.log(reponse);
                return false;
            }else{
                try {
                    return await reponse.json();
                } catch (error) {
                    return [];
                }
            }
        }
 
        this.init();
    }
}
