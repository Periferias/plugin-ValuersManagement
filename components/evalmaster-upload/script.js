
app.component('evalmaster-upload', {
    template: $TEMPLATES['evalmaster-upload'],

    setup(props) {
        const text = Utils.getTexts('evalmaster-upload')
        return { text }
    },

    props: {
        entity: {
            type: Entity,
            requered: true
        }
    },

    data() {
        return {
            newFile: {},
            loading: false,
            maxFileSize: $MAPAS.maxUploadSizeFormatted
        }
    },

    computed: {
        modalTitle() {
            return this.text("Distribuir avaliacoes em lote");
        },
        fileName() {
            return this.newFile.name ?? this.text('Selecione um arquivo');
        }
    },

    methods: {
        setFile() {
            this.newFile = this.$refs.file.files[0];
        },
        upload(modal) {
            this.loading = true;

            let data = {
                group: 'evalmaster',
                description: this.newFile.description
            };

            this.entity.opportunity.upload(this.newFile, data).then((response) => {
                this.newFile = {};
                this.loading = false;
                modal.close();
            });

            return true;
        },
    },
});
