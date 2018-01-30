import View from "../View";
import PreviewTableView from "./PreviewTableView";
import TableView from "../TableView";
import GeocodeBtnView from "./GeocodeBtnView";

export default class PreloadView extends View {

    get header() {
        return this.get('header').prop('checked');
    }

    onCreate() {
        this.results = [];
        this.count = 0;
        this.get('input').change(() => this.parse());
        this.get('radioAddress').click(() => this.updateMode());
        this.get('radioLocality').click(() => this.updateMode());
        this.get('radioCEP').click(() => this.updateMode());
        this.get('header').change(() => this.displayResults());
    }

    parse() {
        this.get('preview').fadeIn('slow');
        this.get('input').parse({
            before: (file) => this.beforeParsing(file),
            error: (err, file, inputElem, reason) => this.onParsingError(err, file, reason),
            config: {
                preview: 6,
                skipEmptyLines: true,
                quote: true,
                header: false,
                skip: this.get('header').prop('checked'),
                complete: (results, file) => this.onCompletedParsing(results, file)
            }
        });
        this.get('exampleContainer').hide();
    }

    countLines() {
        this.count = 0;
        this.displayCount();
        this.get('input').parse({
            config: {
                chunk: (results) => {
                    this.count += results.data.length;
                    this.displayCount();
                }
            }
        });
    }

    displayCount() {
        let price = this.count / 1000;
        this.get('price').html(price.toFixed(2));

        let time = Math.ceil(this.count / 60);
        if (time == 0)
            this.get('time').html('-');
        else
            this.get('time').html(time);
    }

    beforeParsing(file) {
        this.get('label').html(file.name);
        return {action: "continue"};
    }

    getParsedAddress(row, selectedIdxList) {
        let fields = [];
        for (let idx of selectedIdxList) {
            fields.push(this.results[row][idx]);
        }

        return fields.join(', ').trim();
    }

    getAddresses(selectedIdxList) {
        let addresses = [];
        for (let i in this.results) {
            let address = {
                text: this.getParsedAddress(i, selectedIdxList['text']),
                locality: this.getParsedAddress(i, selectedIdxList['locality']),
                postal_code: this.getParsedAddress(i, selectedIdxList['postal_code'])
            };
            addresses.push([
                address.text,
                address.locality,
                address.postal_code,
                View.render(GeocodeBtnView, {address}).container,
            ]);
        }

        return addresses;
    }

    getCepAddresses(selectedIdxList) {
        let addresses = [];
        for (let i in this.results) {
            let address = {
                postal_code: this.getParsedAddress(i, selectedIdxList['postal_code'])
            };
            addresses.push([
                address.postal_code,
                View.render(GeocodeBtnView, {address}).container,
            ]);
        }

        return addresses;
    }

    onParsingError(err, file, reason) {
        console.log(err, file, reason);
    }

    onAddressUpdated(selectedIdxList) {
        let address = this.getParsedAddress(0, selectedIdxList['text']);
        let locality = this.getParsedAddress(0, selectedIdxList['locality']);
        let postal_code = this.getParsedAddress(0, selectedIdxList['postal_code']);

        this.get('indexes').val(JSON.stringify(selectedIdxList))

        if (address.length > 0 && locality.length > 0) {
            this.get('exampleContainer').fadeIn();
            View.render(TableView, this.get('exampleTable'), {
                data: this.getAddresses(selectedIdxList),
                header: ['Endereço', 'Cidade', 'CEP', 'Resultado']
            });
        } else if (address.length == 0 && locality.length == 0 && postal_code.length > 0) {
            this.get('exampleContainer').fadeIn();
            View.render(TableView, this.get('exampleTable'), {
                data: this.getCepAddresses(selectedIdxList),
                header: ['CEP', 'Resultado']
            });
        } else {
            this.get('exampleContainer').fadeOut();
        }
    }

    updateMode() {
        let address = this.get('radioAddress').prop('checked');
        let locality = this.get('radioLocality').prop('checked');
        let postal_code = this.get('radioCEP').prop('checked');

        if (address)
            this.previewTable.setMode('text');
        else if (locality)
            this.previewTable.setMode('locality');
        else if (postal_code)
            this.previewTable.setMode('postal_code');
    }

    onCompletedParsing(results) {
        this.results = results.data;
        this.displayResults();
        this.countLines();
    }

    displayResults() {
        let start = (this.header)? 1: 0;
        let end = this.results.length - ((this.header)? 0: 1);
        let header = (this.header)? this.results[0] : [];
        this.previewTable = View.render(PreviewTableView, this.get('table'), {
            data: this.results.slice(start, end),
            header: header,
            selected: (list) => this.onAddressUpdated(list)
        });
        this.updateMode();
    }
}
