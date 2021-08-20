import './index.scss';
import { Fragment } from 'vue-fragment';
import Config from '@/config/globalConfig';
import Help from '@/components/Help/Help.vue';
import EventMaterials from '@/components/EventMaterials';
import EventMissingMaterials from '@/components/EventMissingMaterials/EventMissingMaterials.vue';
import EventTotals from '@/components/EventTotals';
import ReturnInventorySummary from '@/components/ReturnInventorySummary';

// @vue/component
export default {
    name: 'EventDetailsMaterials',
    props: {
        event: { type: Object, required: true },
    },
    data: () => ({
        showBilling: Config.billingMode !== 'none',
    }),
    computed: {
        hasMaterials() {
            return this.event?.materials?.length > 0;
        },
    },
    render() {
        const { event } = this.$props;
        const { successMessage, error, hasMaterials, showBilling } = this;

        return (
            <div class="EventDetailsMaterials">
                {(error || successMessage) && (
                    <Help message={{ type: 'success', text: successMessage }} error={error} />
                )}
                {event.is_return_inventory_done && (
                    <ReturnInventorySummary
                        eventId={event.id}
                        isDone={event.is_return_inventory_done}
                        materials={event.materials}
                    />
                )}
                {!event.is_return_inventory_done && <EventMissingMaterials eventId={event.id} />}
                {hasMaterials && (
                    <Fragment>
                        <EventMaterials
                            event={event}
                            withRentalPrices={showBilling && event.is_billable}
                            hideDetails={event.materials.length > 16}
                        />
                        <EventTotals
                            event={event}
                            withRentalPrices={showBilling && event.is_billable}
                        />
                    </Fragment>
                )}
            </div>
        );
    },
};
