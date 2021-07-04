import './index.scss';
import Inventory from '@/components/Inventory';

export default {
  name: 'EventReturnMaterialsList',
  props: {
    materials: Array,
    quantities: Array,
    errors: Array,
    isLocked: Boolean,
    displayGroup: {
      default: 'categories',
      validator: (value) => (
        ['categories', 'parks', 'none'].includes(value)
      ),
    },
  },
  computed: {
    awaitedMaterials() {
      return this.materials.map(({ pivot, ...material }) => ({
        ...material, awaited_quantity: pivot.quantity,
      }));
    },

    isAllReturned() {
      return this.materials.every((material) => {
        const _quantities = this.quantities.find(({ id }) => material.id === id);
        if (!_quantities) {
          return false;
        }
        return _quantities.actual === _quantities.awaited_quantity;
      });
    },

    hasBroken() {
      return this.quantities.some(({ broken }) => broken > 0);
    },
  },
  methods: {
    handleChange(id, quantities) {
      this.$emit('change', id, quantities);
    },
  },
  render() {
    const {
      $t: __,
      quantities,
      awaitedMaterials,
      isLocked,
      isAllReturned,
      displayGroup,
      hasBroken,
      handleChange,
    } = this;

    return (
      <div class="EventReturnMaterialsList">
        {isLocked && !isAllReturned && (
          <div class="EventReturnMaterialsList__missing">
            {__('page-event-return.some-material-is-missing')}
          </div>
        )}
        <Inventory
          quantities={quantities}
          materials={awaitedMaterials}
          displayGroup={displayGroup}
          errors={this.errors}
          onChange={handleChange}
          locked={isLocked}
          strict
        />
        {isAllReturned && (
          <div class="EventReturnMaterialsList__all-returned">
            {__('page-event-return.all-material-returned')}
          </div>
        )}
        {hasBroken && (
          <div class="EventReturnMaterialsList__has-broken">
            <i class="fas fa-exclamation-triangle" />{' '}
            {__('page-event-return.some-material-came-back-broken')}
          </div>
        )}
      </div>
    );
  },
};
