Ext.require('Phlexible.fields.Registry');
Ext.require('Phlexible.fields.FieldTypes');
Ext.require('Phlexible.fields.FieldHelper');

/*
 if (this.growHeight) {
 this.setHeight(1);
 this.setHeight(this.el.dom.scrollHeight + 25);

 this.on('keyup', function(c) {
 c.setHeight(1);
 c.setHeight(c.el.dom.scrollHeight + 25);
 //c.el.setHeight(1);
 //c.el.setHeight(c.el.dom.scrollHeight + 25);
 }, this);
 }
 */

Phlexible.fields.Registry.addFactory('textarea', function (parentConfig, item, valueStructure, element, repeatableId) {
    var config = Phlexible.fields.FieldHelper.defaults(parentConfig, item, valueStructure, element, repeatableId);

    Ext.apply(config, {
        xtype: 'textarea',
        minLength: (item.validation.min_length || 0),
        maxLength: (item.validation.max_length || Number.MAX_VALUE),
        vtype: (item.validation.validator || null),
        regex: (item.validation.regexp ? new RegExp(item.validation.regexp, (item.validation.ignore ? 'i' : '') + (item.validation.multiline ? 'm' : '')) : null),

        supportsPrefix: true,
        supportsSuffix: true,
        supportsDiff: true,
        supportsInlineDiff: true,
        supportsUnlink: true,
        supportsRepeatable: true
    });

    var height = parseInt(item.configuration.height, 10);
    if (height) {
        config.height = height;
    }
    else {
        config.grow = true;
    }

    return config;
});

Phlexible.fields.FieldTypes.addField('textarea', {
    titles: {
        de: 'Textarea',
        en: 'Textarea'
    },
    iconCls: 'p-elementtype-field_textarea-icon',
    allowedIn: [
        'tab',
        'accordion',
        'group',
        'referenceroot'
    ],
    allowMap: true,
    defaultValueField: 'default_value_textarea',
    config: {
        labels: {
            field: 1,
            box: 0,
            prefix: 1,
            suffix: 1,
            help: 1
        },
        configuration: {
            required: 1,
            sync: 1,
            width: 1,
            height: 1,
            readonly: 1,
            hide_label: 1,
            sortable: 0
        },
        values: {
            default_text: 0,
            default_number: 0,
            default_textarea: 1,
            default_date: 0,
            default_time: 0,
            default_select: 0,
            default_link: 0,
            default_checkbox: 0,
            default_table: 0,
            source: 0,
            source_values: 0,
            source_function: 0,
            source_datasource: 0,
            text: 0
        },
        validation: {
            text: 1,
            numeric: 0,
            content: 1
        }
    }
});
