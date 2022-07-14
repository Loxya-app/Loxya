import Fragment from '@/components/Fragment';

// @vue/component
export default {
    name: 'Tab',
    props: {
        // - Ces props sont utilisées dans le composant parent 'Tabs'.
        title: { type: String, required: true },
        icon: { type: String, default: null },
        disabled: { type: Boolean, default: false },
        warning: { type: Boolean, default: false },
    },
    render() {
        const { default: children } = this.$slots;

        return (
            <Fragment>
                {children}
            </Fragment>
        );
    },
};
